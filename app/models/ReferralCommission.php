<?php
namespace App\Models;

use App\Core\Model;

/**
 * ReferralCommission Model
 * 
 * Handles referral commissions in the system
 */
class ReferralCommission extends Model {
    // Table name
    protected $table = 'referral_commissions';
    
    // Fields that can be mass assigned
    protected $fillable = [
        'user_id', 'referral_id', 'amount', 'source_transaction_id', 
        'type', 'level', 'status', 'description', 'currency',
        'transaction_id', 'processed_at'
    ];
    
    // Commission status constants
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    
    // Commission type constants
    const TYPE_DEPOSIT = 'deposit';
    const TYPE_MINING = 'mining';
    const TYPE_PURCHASE = 'purchase';
    const TYPE_REGISTRATION = 'registration';
    
    /**
     * Create a referral commission
     *
     * @param int $userId User ID who receives commission
     * @param int $referralId Referred user ID
     * @param float $amount Commission amount
     * @param string $type Commission type
     * @param int $level Referral level
     * @param int $sourceTransactionId Source transaction ID
     * @param string $description Description
     * @param string $currency Currency code
     * @return int|bool ID of created commission or false on failure
     */
    public function createCommission($userId, $referralId, $amount, $type, $level = 1, $sourceTransactionId = null, $description = '', $currency = 'TRX') {
        return $this->create([
            'user_id' => $userId,
            'referral_id' => $referralId,
            'amount' => $amount,
            'source_transaction_id' => $sourceTransactionId,
            'type' => $type,
            'level' => $level,
            'status' => self::STATUS_PENDING,
            'description' => $description,
            'currency' => $currency,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Calculate and create deposit commission
     *
     * @param int $referrerId Referrer ID
     * @param int $referralId Referral ID
     * @param float $depositAmount Deposit amount
     * @param int $transactionId Transaction ID
     * @param string $currency Currency code
     * @return int|bool Commission ID or false on failure
     */
    public function createDepositCommission($referrerId, $referralId, $depositAmount, $transactionId, $currency = 'TRX') {
        // Get commission rate from settings
        $settingModel = new Setting();
        $commissionRate = $settingModel->get('referral_deposit_commission', 5); // Default 5%
        
        // Calculate commission amount
        $commissionAmount = $depositAmount * ($commissionRate / 100);
        
        if ($commissionAmount <= 0) {
            return false;
        }
        
        $description = "Commission for referral deposit of {$depositAmount} {$currency}";
        
        return $this->createCommission(
            $referrerId,
            $referralId,
            $commissionAmount,
            self::TYPE_DEPOSIT,
            1,
            $transactionId,
            $description,
            $currency
        );
    }
    
    /**
     * Calculate and create mining commission
     *
     * @param int $referrerId Referrer ID
     * @param int $referralId Referral ID
     * @param float $miningAmount Mining reward amount
     * @param int $miningRewardId Mining reward ID
     * @param string $currency Currency code
     * @return int|bool Commission ID or false on failure
     */
    public function createMiningCommission($referrerId, $referralId, $miningAmount, $miningRewardId, $currency = 'TRX') {
        // Get commission rate from settings
        $settingModel = new Setting();
        $commissionRate = $settingModel->get('referral_mining_commission', 3); // Default 3%
        
        // Calculate commission amount
        $commissionAmount = $miningAmount * ($commissionRate / 100);
        
        if ($commissionAmount <= 0) {
            return false;
        }
        
        $description = "Commission for referral mining reward of {$miningAmount} {$currency}";
        
        return $this->createCommission(
            $referrerId,
            $referralId,
            $commissionAmount,
            self::TYPE_MINING,
            1,
            $miningRewardId,
            $description,
            $currency
        );
    }
    
    /**
     * Calculate and create purchase commission
     *
     * @param int $referrerId Referrer ID
     * @param int $referralId Referral ID
     * @param float $purchaseAmount Purchase amount
     * @param int $transactionId Transaction ID
     * @param string $currency Currency code
     * @return int|bool Commission ID or false on failure
     */
    public function createPurchaseCommission($referrerId, $referralId, $purchaseAmount, $transactionId, $currency = 'TRX') {
        // Get commission rate from settings
        $settingModel = new Setting();
        $commissionRate = $settingModel->get('referral_purchase_commission', 10); // Default 10%
        
        // Calculate commission amount
        $commissionAmount = $purchaseAmount * ($commissionRate / 100);
        
        if ($commissionAmount <= 0) {
            return false;
        }
        
        $description = "Commission for referral purchase of {$purchaseAmount} {$currency}";
        
        return $this->createCommission(
            $referrerId,
            $referralId,
            $commissionAmount,
            self::TYPE_PURCHASE,
            1,
            $transactionId,
            $description,
            $currency
        );
    }
    
    /**
     * Create multi-level commissions
     *
     * @param int $referralId Referral user ID
     * @param float $amount Base amount
     * @param string $type Commission type
     * @param int $transactionId Source transaction ID
     * @param string $description Description
     * @param string $currency Currency code
     * @return array Created commission IDs
     */
    public function createMultiLevelCommissions($referralId, $amount, $type, $transactionId, $description, $currency = 'TRX') {
        $commissionIds = [];
        $userModel = new User();
        
        // Get multi-level settings
        $settingModel = new Setting();
        $maxLevels = $settingModel->get('referral_levels', 3); // Default 3 levels
        
        // Get commission rates for each level
        $rates = [];
        for ($i = 1; $i <= $maxLevels; $i++) {
            $rates[$i] = $settingModel->get("referral_level{$i}_rate", 0);
        }
        
        // Start with the direct referrer (upline)
        $currentUserId = $referralId;
        $level = 1;
        
        while ($level <= $maxLevels) {
            // Get referrer for current user
            $user = $userModel->find($currentUserId);
            
            if (!$user || !$user->referred_by) {
                break;
            }
            
            $referrerId = $user->referred_by;
            
            // Check if this level has commission
            if (isset($rates[$level]) && $rates[$level] > 0) {
                // Calculate commission amount for this level
                $commissionAmount = $amount * ($rates[$level] / 100);
                
                if ($commissionAmount > 0) {
                    // Create commission
                    $commissionId = $this->createCommission(
                        $referrerId,
                        $referralId,
                        $commissionAmount,
                        $type,
                        $level,
                        $transactionId,
                        $description . " (Level {$level})",
                        $currency
                    );
                    
                    if ($commissionId) {
                        $commissionIds[] = $commissionId;
                    }
                }
            }
            
            // Move up to next level
            $currentUserId = $referrerId;
            $level++;
        }
        
        return $commissionIds;
    }
    
    /**
     * Get user's commissions
     *
     * @param int $userId User ID
     * @param string $status Optional status filter
     * @param int $limit Maximum records to return
     * @param int $offset Offset for pagination
     * @return array User's commissions
     */
    public function getUserCommissions($userId, $status = null, $limit = 20, $offset = 0) {
        $query = "SELECT c.*, u.username as referral_username 
                 FROM {$this->table} c
                 LEFT JOIN users u ON c.referral_id = u.id
                 WHERE c.user_id = :user_id";
        
        $params = ['user_id' => $userId];
        
        if ($status !== null) {
            $query .= " AND c.status = :status";
            $params['status'] = $status;
        }
        
        $query .= " ORDER BY c.created_at DESC LIMIT :limit OFFSET :offset";
        
        $params['limit'] = $limit;
        $params['offset'] = $offset;
        
        return $this->raw($query, $params);
    }
    
    /**
     * Get user's total commissions
     *
     * @param int $userId User ID
     * @param string $status Optional status filter
     * @return float Total commissions
     */
    public function getUserTotalCommissions($userId, $status = null) {
        $query = "SELECT SUM(amount) as total FROM {$this->table} 
                 WHERE user_id = :user_id";
        
        $params = ['user_id' => $userId];
        
        if ($status !== null) {
            $query .= " AND status = :status";
            $params['status'] = $status;
        }
        
        $result = $this->raw($query, $params, false);
        
        return $result ? floatval($result->total) : 0;
    }
    
    /**
     * Count user's commissions
     *
     * @param int $userId User ID
     * @param string $status Optional status filter
     * @return int Number of commissions
     */
    public function countUserCommissions($userId, $status = null) {
        $query = "SELECT COUNT(*) as count FROM {$this->table} 
                 WHERE user_id = :user_id";
        
        $params = ['user_id' => $userId];
        
        if ($status !== null) {
            $query .= " AND status = :status";
            $params['status'] = $status;
        }
        
        $result = $this->raw($query, $params, false);
        
        return $result ? intval($result->count) : 0;
    }
    
    /**
     * Get pending commissions
     *
     * @param int $limit Maximum number of records
     * @return array Pending commissions
     */
    public function getPendingCommissions($limit = 100) {
        $query = "SELECT c.*, u.username, u.wallet_address 
                 FROM {$this->table} c
                 JOIN users u ON c.user_id = u.id
                 WHERE c.status = :status
                 ORDER BY c.created_at ASC
                 LIMIT :limit";
        
        return $this->raw($query, [
            'status' => self::STATUS_PENDING,
            'limit' => $limit
        ]);
    }
    
    /**
     * Mark commissions as processing
     *
     * @param array $commissionIds Array of commission IDs
     * @return bool Success or failure
     */
    public function markAsProcessing($commissionIds) {
        if (empty($commissionIds)) {
            return false;
        }
        
        $placeholders = implode(',', array_fill(0, count($commissionIds), '?'));
        
        $query = "UPDATE {$this->table} 
                 SET status = ?, updated_at = ? 
                 WHERE id IN ({$placeholders})";
        
        $params = array_merge([self::STATUS_PROCESSING, date('Y-m-d H:i:s')], $commissionIds);
        
        return $this->execute($query, $params);
    }
    
    /**
     * Complete commission processing
     *
     * @param int $commissionId Commission ID
     * @param string $transactionId Blockchain transaction ID
     * @return bool Success or failure
     */
    public function completeCommission($commissionId, $transactionId) {
        return $this->update($commissionId, [
            'status' => self::STATUS_COMPLETED,
            'transaction_id' => $transactionId,
            'processed_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Mark commission as failed
     *
     * @param int $commissionId Commission ID
     * @param string $reason Failure reason
     * @return bool Success or failure
     */
    public function failCommission($commissionId, $reason = null) {
        $data = [
            'status' => self::STATUS_FAILED,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        if ($reason) {
            $data['description'] = $reason;
        }
        
        return $this->update($commissionId, $data);
    }
    
    /**
     * Get commission statistics
     *
     * @return array Statistics
     */
    public function getCommissionStats() {
        $stats = [
            'total_commissions' => 0,
            'pending_commissions' => 0,
            'completed_commissions' => 0,
            'daily_commissions' => 0,
            'monthly_commissions' => 0,
            'by_type' => [],
            'by_level' => []
        ];
        
        // Total commissions
        $query = "SELECT SUM(amount) as total FROM {$this->table}";
        $result = $this->raw($query, [], false);
        $stats['total_commissions'] = $result ? floatval($result->total) : 0;
        
        // Pending commissions
        $query = "SELECT SUM(amount) as total FROM {$this->table} WHERE status = :status";
        $result = $this->raw($query, ['status' => self::STATUS_PENDING], false);
        $stats['pending_commissions'] = $result ? floatval($result->total) : 0;
        
        // Completed commissions
        $query = "SELECT SUM(amount) as total FROM {$this->table} WHERE status = :status";
        $result = $this->raw($query, ['status' => self::STATUS_COMPLETED], false);
        $stats['completed_commissions'] = $result ? floatval($result->total) : 0;
        
        // Daily commissions (today)
        $today = date('Y-m-d');
        $query = "SELECT SUM(amount) as total FROM {$this->table} 
                 WHERE DATE(created_at) = :date";
        $result = $this->raw($query, ['date' => $today], false);
        $stats['daily_commissions'] = $result ? floatval($result->total) : 0;
        
        // Monthly commissions (current month)
        $startOfMonth = date('Y-m-01');
        $endOfMonth = date('Y-m-t');
        $query = "SELECT SUM(amount) as total FROM {$this->table} 
                 WHERE created_at BETWEEN :start AND :end";
        $result = $this->raw($query, [
            'start' => $startOfMonth . ' 00:00:00',
            'end' => $endOfMonth . ' 23:59:59'
        ], false);
        $stats['monthly_commissions'] = $result ? floatval($result->total) : 0;
        
        // By type
        $query = "SELECT type, SUM(amount) as total FROM {$this->table} GROUP BY type";
        $results = $this->raw($query);
        foreach ($results as $row) {
            $stats['by_type'][$row->type] = floatval($row->total);
        }
        
        // By level
        $query = "SELECT level, SUM(amount) as total FROM {$this->table} GROUP BY level";
        $results = $this->raw($query);
        foreach ($results as $row) {
            $stats['by_level'][$row->level] = floatval($row->total);
        }
        
        return $stats;
    }
    
    /**
     * Search commissions
     *
     * @param array $filters Search filters
     * @param int $limit Limit
     * @param int $offset Offset
     * @return array Commissions
     */
    public function searchCommissions($filters = [], $limit = 20, $offset = 0) {
        $query = "SELECT c.*, u.username as user_username, r.username as referral_username 
                 FROM {$this->table} c
                 LEFT JOIN users u ON c.user_id = u.id
                 LEFT JOIN users r ON c.referral_id = r.id
                 WHERE 1=1";
        
        $params = [];
        
        if (isset($filters['user_id'])) {
            $query .= " AND c.user_id = :user_id";
            $params['user_id'] = $filters['user_id'];
        }
        
        if (isset($filters['referral_id'])) {
            $query .= " AND c.referral_id = :referral_id";
            $params['referral_id'] = $filters['referral_id'];
        }
        
        if (isset($filters['username'])) {
            $query .= " AND u.username LIKE :username";
            $params['username'] = '%' . $filters['username'] . '%';
        }
        
        if (isset($filters['referral_username'])) {
            $query .= " AND r.username LIKE :referral_username";
            $params['referral_username'] = '%' . $filters['referral_username'] . '%';
        }
        
        if (isset($filters['status'])) {
            $query .= " AND c.status = :status";
            $params['status'] = $filters['status'];
        }
        
        if (isset($filters['type'])) {
            $query .= " AND c.type = :type";
            $params['type'] = $filters['type'];
        }
        
        if (isset($filters['level'])) {
            $query .= " AND c.level = :level";
            $params['level'] = $filters['level'];
        }
        
        if (isset($filters['date_from'])) {
            $query .= " AND c.created_at >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }
        
        if (isset($filters['date_to'])) {
            $query .= " AND c.created_at <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }
        
        $query .= " ORDER BY c.created_at DESC LIMIT :limit OFFSET :offset";
        
        $params['limit'] = $limit;
        $params['offset'] = $offset;
        
        return $this->raw($query, $params);
    }
    
    /**
     * Get a readable label for commission types
     *
     * @param string $type Commission type
     * @return string Type label
     */
    public static function getTypeLabel($type) {
        $labels = [
            self::TYPE_DEPOSIT => 'Deposit Commission',
            self::TYPE_MINING => 'Mining Commission',
            self::TYPE_PURCHASE => 'Purchase Commission',
            self::TYPE_REGISTRATION => 'Registration Commission'
        ];
        
        return $labels[$type] ?? 'Unknown';
    }
    
    /**
     * Get a readable label for commission statuses
     *
     * @param string $status Commission status
     * @return string Status label
     */
    public static function getStatusLabel($status) {
        $labels = [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_PROCESSING => 'Processing',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_FAILED => 'Failed'
        ];
        
        return $labels[$status] ?? 'Unknown';
    }
} 