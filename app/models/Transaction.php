<?php
namespace App\Models;

use App\Core\Model;

/**
 * Transaction Model
 * 
 * Handles transaction-related database operations
 */
class Transaction extends Model {
    // Table name
    protected $table = 'transactions';
    
    // Fields that can be mass assigned
    protected $fillable = [
        'user_id', 'type', 'amount', 'fee', 'currency', 'status', 'description', 
        'payment_method', 'reference_id', 'tx_hash', 'wallet_address'
    ];
    
    /**
     * Transaction types
     */
    const TYPE_DEPOSIT = 'deposit';
    const TYPE_WITHDRAWAL = 'withdrawal';
    const TYPE_MINING_REWARD = 'mining_reward';
    const TYPE_REFERRAL_COMMISSION = 'referral_commission';
    const TYPE_PURCHASE = 'purchase';
    
    /**
     * Transaction statuses
     */
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';
    
    /**
     * Create a new transaction
     *
     * @param array $data Transaction data
     * @return int|bool Transaction ID or false on failure
     */
    public function createTransaction(array $data) {
        // Set defaults if not provided
        if (!isset($data['status'])) {
            $data['status'] = self::STATUS_PENDING;
        }
        
        if (!isset($data['fee'])) {
            $data['fee'] = 0;
        }
        
        if (!isset($data['created_at'])) {
            $data['created_at'] = date('Y-m-d H:i:s');
        }
        
        return $this->create($data);
    }
    
    /**
     * Get user's transactions
     *
     * @param int $userId User ID
     * @param array $filters Optional filters (type, status, startDate, endDate)
     * @param int $limit Limit (optional)
     * @param int $offset Offset (optional)
     * @return array User's transactions
     */
    public function getUserTransactions($userId, array $filters = [], $limit = null, $offset = null) {
        $sql = "SELECT * FROM {$this->table} WHERE user_id = :user_id";
        $params = ['user_id' => $userId];
        
        // Apply filters
        if (!empty($filters['type'])) {
            $sql .= " AND type = :type";
            $params['type'] = $filters['type'];
        }
        
        if (!empty($filters['status'])) {
            $sql .= " AND status = :status";
            $params['status'] = $filters['status'];
        }
        
        if (!empty($filters['startDate'])) {
            $sql .= " AND created_at >= :start_date";
            $params['start_date'] = $filters['startDate'];
        }
        
        if (!empty($filters['endDate'])) {
            $sql .= " AND created_at <= :end_date";
            $params['end_date'] = $filters['endDate'];
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        if ($limit !== null) {
            $sql .= " LIMIT :limit";
            if ($offset !== null) {
                $sql .= " OFFSET :offset";
            }
        }
        
        if ($limit !== null) {
            $params['limit'] = (int) $limit;
            if ($offset !== null) {
                $params['offset'] = (int) $offset;
            }
        }
        
        return $this->raw($sql, $params);
    }
    
    /**
     * Get transaction by reference ID
     *
     * @param string $referenceId Reference ID
     * @return object|null Transaction data or null if not found
     */
    public function getByReferenceId($referenceId) {
        return $this->findBy('reference_id', $referenceId, false);
    }
    
    /**
     * Get transaction by transaction hash
     *
     * @param string $txHash Transaction hash
     * @return object|null Transaction data or null if not found
     */
    public function getByTxHash($txHash) {
        return $this->findBy('tx_hash', $txHash, false);
    }
    
    /**
     * Update transaction status
     *
     * @param int $id Transaction ID
     * @param string $status New status
     * @param string $txHash Transaction hash (optional)
     * @return bool Success or failure
     */
    public function updateStatus($id, $status, $txHash = null) {
        $data = [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        if ($txHash !== null) {
            $data['tx_hash'] = $txHash;
        }
        
        return $this->update($id, $data);
    }
    
    /**
     * Process pending transactions
     * 
     * This method checks for pending transactions and processes them accordingly
     *
     * @return array Processed transactions
     */
    public function processPendingTransactions() {
        $sql = "SELECT * FROM {$this->table} WHERE status = :status ORDER BY created_at ASC";
        $pendingTransactions = $this->raw($sql, ['status' => self::STATUS_PENDING]);
        
        $processedTransactions = [];
        
        if (!$pendingTransactions) {
            return $processedTransactions;
        }
        
        $userModel = new \App\Models\User();
        
        foreach ($pendingTransactions as $transaction) {
            // Set transaction to processing status
            $this->updateStatus($transaction->id, self::STATUS_PROCESSING);
            
            // Process based on transaction type
            switch ($transaction->type) {
                case self::TYPE_DEPOSIT:
                    // Update user balance for deposits
                    if ($userModel->updateBalance($transaction->user_id, $transaction->amount)) {
                        $this->updateStatus($transaction->id, self::STATUS_COMPLETED);
                        $processedTransactions[] = $transaction;
                    } else {
                        $this->updateStatus($transaction->id, self::STATUS_FAILED);
                    }
                    break;
                
                case self::TYPE_WITHDRAWAL:
                    // For withdrawals, we'll assume they need manual processing or are handled by an external system
                    // In a real system, you would integrate with a payment processor here
                    $this->updateStatus($transaction->id, self::STATUS_PROCESSING);
                    break;
                    
                case self::TYPE_PURCHASE:
                    // For purchases, we'll process the order
                    $miningPackageModel = new \App\Models\MiningPackage();
                    $userMiningPowerModel = new \App\Models\UserMiningPower();
                    
                    // The reference_id for a purchase would typically be the package ID
                    if ($transaction->reference_id) {
                        $package = $miningPackageModel->find($transaction->reference_id);
                        
                        if ($package) {
                            // Add mining power to user
                            $expiresAt = date('Y-m-d H:i:s', strtotime("+{$package->duration} days"));
                            
                            $miningPowerId = $userMiningPowerModel->create([
                                'user_id' => $transaction->user_id,
                                'package_id' => $package->id,
                                'mining_power' => $package->mining_power,
                                'status' => 'active',
                                'expires_at' => $expiresAt
                            ]);
                            
                            if ($miningPowerId) {
                                $this->updateStatus($transaction->id, self::STATUS_COMPLETED);
                                $processedTransactions[] = $transaction;
                            } else {
                                $this->updateStatus($transaction->id, self::STATUS_FAILED);
                            }
                        } else {
                            $this->updateStatus($transaction->id, self::STATUS_FAILED);
                        }
                    } else {
                        $this->updateStatus($transaction->id, self::STATUS_FAILED);
                    }
                    break;
                
                default:
                    // For other transaction types, we'll move them to completed
                    $this->updateStatus($transaction->id, self::STATUS_COMPLETED);
                    $processedTransactions[] = $transaction;
                    break;
            }
        }
        
        return $processedTransactions;
    }
    
    /**
     * Get transaction statistics
     *
     * @param string $type Transaction type (optional)
     * @param string $startDate Start date (optional)
     * @param string $endDate End date (optional)
     * @return array Transaction statistics
     */
    public function getTransactionStats($type = null, $startDate = null, $endDate = null) {
        $whereClause = "";
        $params = [];
        
        if ($type !== null) {
            $whereClause .= " WHERE type = :type";
            $params['type'] = $type;
        }
        
        if ($startDate !== null) {
            $whereClause .= ($whereClause === "" ? " WHERE" : " AND") . " created_at >= :start_date";
            $params['start_date'] = $startDate;
        }
        
        if ($endDate !== null) {
            $whereClause .= ($whereClause === "" ? " WHERE" : " AND") . " created_at <= :end_date";
            $params['end_date'] = $endDate;
        }
        
        // Get total volume
        $sql = "SELECT IFNULL(SUM(amount), 0) as total_volume FROM {$this->table}{$whereClause}";
        $totalVolume = $this->raw($sql, $params)[0]->total_volume ?? 0;
        
        // Get total fees
        $sql = "SELECT IFNULL(SUM(fee), 0) as total_fees FROM {$this->table}{$whereClause}";
        $totalFees = $this->raw($sql, $params)[0]->total_fees ?? 0;
        
        // Get count by status
        $sql = "SELECT status, COUNT(*) as count FROM {$this->table}{$whereClause} GROUP BY status";
        $statusCounts = $this->raw($sql, $params);
        
        $countByStatus = [
            self::STATUS_PENDING => 0,
            self::STATUS_PROCESSING => 0,
            self::STATUS_COMPLETED => 0,
            self::STATUS_FAILED => 0,
            self::STATUS_CANCELLED => 0
        ];
        
        foreach ($statusCounts as $statusCount) {
            $countByStatus[$statusCount->status] = $statusCount->count;
        }
        
        // Get top currencies
        $sql = "SELECT currency, COUNT(*) as count, IFNULL(SUM(amount), 0) as volume 
                FROM {$this->table}{$whereClause} 
                GROUP BY currency 
                ORDER BY count DESC
                LIMIT 5";
        $topCurrencies = $this->raw($sql, $params);
        
        return [
            'total_volume' => $totalVolume,
            'total_fees' => $totalFees,
            'count_by_status' => $countByStatus,
            'top_currencies' => $topCurrencies
        ];
    }
    
    /**
     * Generate a unique reference ID for transactions
     *
     * @param string $prefix Prefix for the reference ID
     * @return string Unique reference ID
     */
    public function generateReferenceId($prefix = 'TRX') {
        $timestamp = time();
        $randomStr = bin2hex(random_bytes(3)); // 6 characters
        $referenceId = $prefix . $timestamp . strtoupper($randomStr);
        
        // Check if already exists and regenerate if needed
        while ($this->getByReferenceId($referenceId)) {
            $randomStr = bin2hex(random_bytes(3));
            $referenceId = $prefix . $timestamp . strtoupper($randomStr);
        }
        
        return $referenceId;
    }
    
    /**
     * Count user's transactions by type or status
     *
     * @param int $userId User ID
     * @param string|null $type Transaction type
     * @param string|null $status Transaction status
     * @return int Count
     */
    public function countUserTransactions($userId, $type = null, $status = null) {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE user_id = :user_id";
        $params = ['user_id' => $userId];
        
        if ($type !== null) {
            $sql .= " AND type = :type";
            $params['type'] = $type;
        }
        
        if ($status !== null) {
            $sql .= " AND status = :status";
            $params['status'] = $status;
        }
        
        $result = $this->raw($sql, $params);
        return $result[0]->count ?? 0;
    }
    
    /**
     * Get total transaction amount for a user by type and status
     *
     * @param int $userId User ID
     * @param string|null $type Transaction type
     * @param string|null $status Transaction status
     * @param string|null $currency Currency filter
     * @return float Total amount
     */
    public function getTotalUserTransactionAmount($userId, $type = null, $status = null, $currency = null) {
        $sql = "SELECT IFNULL(SUM(amount), 0) as total FROM {$this->table} WHERE user_id = :user_id";
        $params = ['user_id' => $userId];
        
        if ($type !== null) {
            $sql .= " AND type = :type";
            $params['type'] = $type;
        }
        
        if ($status !== null) {
            $sql .= " AND status = :status";
            $params['status'] = $status;
        }
        
        if ($currency !== null) {
            $sql .= " AND currency = :currency";
            $params['currency'] = $currency;
        }
        
        $result = $this->raw($sql, $params);
        return $result[0]->total ?? 0;
    }
    
    /**
     * Get recent transactions (for admin dashboard, etc.)
     *
     * @param int $limit Number of transactions to retrieve
     * @return array Recent transactions
     */
    public function getRecentTransactions($limit = 10) {
        $sql = "SELECT t.*, u.username FROM {$this->table} t
                LEFT JOIN users u ON t.user_id = u.id
                ORDER BY t.created_at DESC
                LIMIT :limit";
        return $this->raw($sql, ['limit' => (int) $limit]);
    }
    
    /**
     * Cancel a pending transaction
     *
     * @param int $id Transaction ID
     * @param string $reason Cancellation reason (optional)
     * @return bool Success or failure
     */
    public function cancelTransaction($id, $reason = null) {
        $transaction = $this->find($id);
        
        if (!$transaction || $transaction->status !== self::STATUS_PENDING) {
            return false;
        }
        
        $data = [
            'status' => self::STATUS_CANCELLED,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        if ($reason !== null) {
            $data['description'] = $transaction->description . ' | Cancelled: ' . $reason;
        }
        
        return $this->update($id, $data);
    }
    
    /**
     * Get transaction by ID and user ID (for security)
     *
     * @param int $id Transaction ID
     * @param int $userId User ID
     * @return object|null Transaction data or null if not found
     */
    public function getUserTransaction($id, $userId) {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id AND user_id = :user_id";
        $params = ['id' => $id, 'user_id' => $userId];
        $result = $this->raw($sql, $params);
        
        return $result[0] ?? null;
    }
} 