<?php
namespace App\Models;

use App\Core\Model;

/**
 * Wallet Model
 * 
 * Handles user cryptocurrency wallets
 */
class Wallet extends Model {
    // Table name
    protected $table = 'wallets';
    
    // Fields that can be mass assigned
    protected $fillable = [
        'user_id', 'currency', 'balance', 'locked_balance', 
        'wallet_address', 'wallet_tag', 'last_deposit_at', 
        'last_withdrawal_at', 'status'
    ];
    
    // Wallet status constants
    const STATUS_ACTIVE = 'active';
    const STATUS_DISABLED = 'disabled';
    const STATUS_FROZEN = 'frozen';
    
    /**
     * Create a new wallet for a user
     *
     * @param int $userId User ID
     * @param string $currency Currency code
     * @param string $walletAddress Wallet address (optional)
     * @param string $walletTag Wallet tag/memo (optional, for some currencies)
     * @return int|bool Wallet ID or false on failure
     */
    public function createWallet($userId, $currency, $walletAddress = null, $walletTag = null) {
        // Check if wallet already exists
        $existingWallet = $this->getUserWallet($userId, $currency);
        if ($existingWallet) {
            return false;
        }
        
        return $this->create([
            'user_id' => $userId,
            'currency' => $currency,
            'balance' => 0,
            'locked_balance' => 0,
            'wallet_address' => $walletAddress,
            'wallet_tag' => $walletTag,
            'status' => self::STATUS_ACTIVE,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Get user's wallet by currency
     *
     * @param int $userId User ID
     * @param string $currency Currency code
     * @return object|null Wallet data or null if not found
     */
    public function getUserWallet($userId, $currency) {
        $query = "SELECT * FROM {$this->table} 
                 WHERE user_id = :user_id AND currency = :currency 
                 LIMIT 1";
        
        return $this->raw($query, [
            'user_id' => $userId,
            'currency' => $currency
        ], false);
    }
    
    /**
     * Get user's wallets
     *
     * @param int $userId User ID
     * @return array User's wallets
     */
    public function getUserWallets($userId) {
        return $this->where('user_id', $userId);
    }
    
    /**
     * Check if a user has a wallet for the specified currency
     *
     * @param int $userId User ID
     * @param string $currency Currency code
     * @return bool Whether user has a wallet for the currency
     */
    public function hasWallet($userId, $currency) {
        return $this->getUserWallet($userId, $currency) !== null;
    }
    
    /**
     * Get wallet by ID
     *
     * @param int $walletId Wallet ID
     * @return object|null Wallet data or null if not found
     */
    public function getWalletById($walletId) {
        return $this->find($walletId);
    }
    
    /**
     * Get wallet balance
     *
     * @param int $userId User ID
     * @param string $currency Currency code
     * @return float Wallet balance
     */
    public function getBalance($userId, $currency) {
        $wallet = $this->getUserWallet($userId, $currency);
        
        return $wallet ? floatval($wallet->balance) : 0;
    }
    
    /**
     * Get wallet locked balance
     *
     * @param int $userId User ID
     * @param string $currency Currency code
     * @return float Wallet locked balance
     */
    public function getLockedBalance($userId, $currency) {
        $wallet = $this->getUserWallet($userId, $currency);
        
        return $wallet ? floatval($wallet->locked_balance) : 0;
    }
    
    /**
     * Get wallet available balance (total - locked)
     *
     * @param int $userId User ID
     * @param string $currency Currency code
     * @return float Wallet available balance
     */
    public function getAvailableBalance($userId, $currency) {
        $wallet = $this->getUserWallet($userId, $currency);
        
        if (!$wallet) {
            return 0;
        }
        
        return floatval($wallet->balance) - floatval($wallet->locked_balance);
    }
    
    /**
     * Credit (increase) wallet balance
     *
     * @param int $userId User ID
     * @param string $currency Currency code
     * @param float $amount Amount to credit
     * @param bool $createIfMissing Whether to create wallet if it doesn't exist
     * @return bool Success or failure
     */
    public function credit($userId, $currency, $amount, $createIfMissing = true) {
        if ($amount <= 0) {
            return false;
        }
        
        $wallet = $this->getUserWallet($userId, $currency);
        
        if (!$wallet) {
            if (!$createIfMissing) {
                return false;
            }
            
            // Create wallet for user
            $walletId = $this->createWallet($userId, $currency);
            if (!$walletId) {
                return false;
            }
            
            $wallet = $this->getWalletById($walletId);
        }
        
        // Update balance and last deposit timestamp
        $newBalance = floatval($wallet->balance) + $amount;
        
        return $this->update($wallet->id, [
            'balance' => $newBalance,
            'last_deposit_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Debit (decrease) wallet balance
     *
     * @param int $userId User ID
     * @param string $currency Currency code
     * @param float $amount Amount to debit
     * @return bool Success or failure
     */
    public function debit($userId, $currency, $amount) {
        if ($amount <= 0) {
            return false;
        }
        
        $wallet = $this->getUserWallet($userId, $currency);
        
        if (!$wallet) {
            return false;
        }
        
        // Check if there's enough available balance
        $availableBalance = floatval($wallet->balance) - floatval($wallet->locked_balance);
        
        if ($availableBalance < $amount) {
            return false;
        }
        
        // Update balance and last withdrawal timestamp
        $newBalance = floatval($wallet->balance) - $amount;
        
        return $this->update($wallet->id, [
            'balance' => $newBalance,
            'last_withdrawal_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Lock wallet balance (reserve for a pending operation)
     *
     * @param int $userId User ID
     * @param string $currency Currency code
     * @param float $amount Amount to lock
     * @return bool Success or failure
     */
    public function lockBalance($userId, $currency, $amount) {
        if ($amount <= 0) {
            return false;
        }
        
        $wallet = $this->getUserWallet($userId, $currency);
        
        if (!$wallet) {
            return false;
        }
        
        // Check if there's enough available balance
        $availableBalance = floatval($wallet->balance) - floatval($wallet->locked_balance);
        
        if ($availableBalance < $amount) {
            return false;
        }
        
        // Lock the specified amount
        $newLockedBalance = floatval($wallet->locked_balance) + $amount;
        
        return $this->update($wallet->id, [
            'locked_balance' => $newLockedBalance,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Unlock wallet balance
     *
     * @param int $userId User ID
     * @param string $currency Currency code
     * @param float $amount Amount to unlock
     * @return bool Success or failure
     */
    public function unlockBalance($userId, $currency, $amount) {
        if ($amount <= 0) {
            return false;
        }
        
        $wallet = $this->getUserWallet($userId, $currency);
        
        if (!$wallet) {
            return false;
        }
        
        // Check if there's enough locked balance
        if (floatval($wallet->locked_balance) < $amount) {
            return false;
        }
        
        // Unlock the specified amount
        $newLockedBalance = floatval($wallet->locked_balance) - $amount;
        
        return $this->update($wallet->id, [
            'locked_balance' => $newLockedBalance,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Update wallet address
     *
     * @param int $userId User ID
     * @param string $currency Currency code
     * @param string $walletAddress New wallet address
     * @param string $walletTag New wallet tag/memo (optional)
     * @return bool Success or failure
     */
    public function updateWalletAddress($userId, $currency, $walletAddress, $walletTag = null) {
        $wallet = $this->getUserWallet($userId, $currency);
        
        if (!$wallet) {
            return false;
        }
        
        $data = [
            'wallet_address' => $walletAddress,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        if ($walletTag !== null) {
            $data['wallet_tag'] = $walletTag;
        }
        
        return $this->update($wallet->id, $data);
    }
    
    /**
     * Disable wallet
     *
     * @param int $userId User ID
     * @param string $currency Currency code
     * @return bool Success or failure
     */
    public function disableWallet($userId, $currency) {
        $wallet = $this->getUserWallet($userId, $currency);
        
        if (!$wallet) {
            return false;
        }
        
        return $this->update($wallet->id, [
            'status' => self::STATUS_DISABLED,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Enable wallet
     *
     * @param int $userId User ID
     * @param string $currency Currency code
     * @return bool Success or failure
     */
    public function enableWallet($userId, $currency) {
        $wallet = $this->getUserWallet($userId, $currency);
        
        if (!$wallet) {
            return false;
        }
        
        return $this->update($wallet->id, [
            'status' => self::STATUS_ACTIVE,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Freeze wallet
     *
     * @param int $userId User ID
     * @param string $currency Currency code
     * @return bool Success or failure
     */
    public function freezeWallet($userId, $currency) {
        $wallet = $this->getUserWallet($userId, $currency);
        
        if (!$wallet) {
            return false;
        }
        
        return $this->update($wallet->id, [
            'status' => self::STATUS_FROZEN,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Create default wallets for a user
     *
     * @param int $userId User ID
     * @return array Created wallet IDs
     */
    public function createDefaultWallets($userId) {
        $defaultCurrencies = ['TRX', 'USDT', 'BTC'];
        $createdWallets = [];
        
        foreach ($defaultCurrencies as $currency) {
            $walletId = $this->createWallet($userId, $currency);
            if ($walletId) {
                $createdWallets[] = $walletId;
            }
        }
        
        return $createdWallets;
    }
    
    /**
     * Transfer funds between users
     *
     * @param int $fromUserId Source user ID
     * @param int $toUserId Destination user ID
     * @param string $currency Currency code
     * @param float $amount Amount to transfer
     * @return bool Success or failure
     */
    public function transferBetweenUsers($fromUserId, $toUserId, $currency, $amount) {
        if ($amount <= 0 || $fromUserId === $toUserId) {
            return false;
        }
        
        // Start transaction
        $this->beginTransaction();
        
        try {
            // Debit from source user
            $debitResult = $this->debit($fromUserId, $currency, $amount);
            
            if (!$debitResult) {
                $this->rollback();
                return false;
            }
            
            // Credit to destination user
            $creditResult = $this->credit($toUserId, $currency, $amount, true);
            
            if (!$creditResult) {
                $this->rollback();
                return false;
            }
            
            // Commit transaction
            $this->commit();
            return true;
        } catch (\Exception $e) {
            $this->rollback();
            return false;
        }
    }
    
    /**
     * Get wallets statistics
     *
     * @return array Statistics
     */
    public function getWalletStats() {
        $stats = [
            'total_wallets' => 0,
            'active_wallets' => 0,
            'total_balance' => [],
            'locked_balance' => []
        ];
        
        // Total wallets
        $query = "SELECT COUNT(*) as count FROM {$this->table}";
        $result = $this->raw($query, [], false);
        $stats['total_wallets'] = $result ? intval($result->count) : 0;
        
        // Active wallets
        $query = "SELECT COUNT(*) as count FROM {$this->table} WHERE status = :status";
        $result = $this->raw($query, ['status' => self::STATUS_ACTIVE], false);
        $stats['active_wallets'] = $result ? intval($result->count) : 0;
        
        // Balances by currency
        $query = "SELECT currency, SUM(balance) as total, SUM(locked_balance) as locked 
                 FROM {$this->table} 
                 GROUP BY currency";
        $results = $this->raw($query);
        
        foreach ($results as $row) {
            $stats['total_balance'][$row->currency] = floatval($row->total);
            $stats['locked_balance'][$row->currency] = floatval($row->locked);
        }
        
        return $stats;
    }
    
    /**
     * Search wallets
     *
     * @param array $filters Search filters
     * @param int $limit Maximum number of records
     * @param int $offset Offset for pagination
     * @return array Wallets matching criteria
     */
    public function searchWallets($filters = [], $limit = 20, $offset = 0) {
        $query = "SELECT w.*, u.username 
                 FROM {$this->table} w
                 JOIN users u ON w.user_id = u.id
                 WHERE 1=1";
        
        $params = [];
        
        if (isset($filters['user_id'])) {
            $query .= " AND w.user_id = :user_id";
            $params['user_id'] = $filters['user_id'];
        }
        
        if (isset($filters['username'])) {
            $query .= " AND u.username LIKE :username";
            $params['username'] = '%' . $filters['username'] . '%';
        }
        
        if (isset($filters['currency'])) {
            $query .= " AND w.currency = :currency";
            $params['currency'] = $filters['currency'];
        }
        
        if (isset($filters['status'])) {
            $query .= " AND w.status = :status";
            $params['status'] = $filters['status'];
        }
        
        if (isset($filters['min_balance'])) {
            $query .= " AND w.balance >= :min_balance";
            $params['min_balance'] = $filters['min_balance'];
        }
        
        if (isset($filters['max_balance'])) {
            $query .= " AND w.balance <= :max_balance";
            $params['max_balance'] = $filters['max_balance'];
        }
        
        if (isset($filters['wallet_address'])) {
            $query .= " AND w.wallet_address LIKE :wallet_address";
            $params['wallet_address'] = '%' . $filters['wallet_address'] . '%';
        }
        
        $query .= " ORDER BY u.username ASC, w.currency ASC LIMIT :limit OFFSET :offset";
        
        $params['limit'] = $limit;
        $params['offset'] = $offset;
        
        return $this->raw($query, $params);
    }
    
    /**
     * Get a readable label for wallet statuses
     *
     * @param string $status Status code
     * @return string Status label
     */
    public static function getStatusLabel($status) {
        $labels = [
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_DISABLED => 'Disabled',
            self::STATUS_FROZEN => 'Frozen'
        ];
        
        return $labels[$status] ?? 'Unknown';
    }
} 