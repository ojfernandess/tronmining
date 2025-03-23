<?php
namespace App\Models;

use App\Core\Model;

/**
 * MiningPower Model
 * 
 * Handles mining power-related database operations
 */
class MiningPower extends Model {
    // Table name
    protected $table = 'mining_powers';
    
    // Fields that can be mass assigned
    protected $fillable = [
        'user_id', 'package_id', 'amount', 'status', 'mining_power', 'daily_reward_rate',
        'start_date', 'end_date', 'currency'
    ];
    
    /**
     * Mining power statuses
     */
    const STATUS_ACTIVE = 'active';
    const STATUS_PENDING = 'pending';
    const STATUS_EXPIRED = 'expired';
    const STATUS_CANCELLED = 'cancelled';
    
    /**
     * Purchase a mining power package
     *
     * @param int $userId User ID
     * @param int $packageId Package ID
     * @param float $amount Purchase amount
     * @param string $currency Currency
     * @return int|false Mining power ID or false on failure
     */
    public function purchaseMiningPower($userId, $packageId, $amount, $currency) {
        // Get package details
        $packageModel = new MiningPackage();
        $package = $packageModel->find($packageId);
        
        if (!$package) {
            return false;
        }
        
        // Get user wallet
        $walletModel = new Wallet();
        $wallet = $walletModel->getUserWallet($userId, $currency);
        
        if (!$wallet || $wallet->balance < $amount) {
            return false;
        }
        
        // Calculate dates
        $startDate = date('Y-m-d H:i:s');
        $endDate = date('Y-m-d H:i:s', strtotime("+{$package->duration} days"));
        
        // Create reference ID for transaction
        $transactionModel = new Transaction();
        $referenceId = $transactionModel->generateReferenceId('PKG');
        
        try {
            // Begin transaction
            $this->beginTransaction();
            
            // Deduct amount from user wallet
            $deducted = $walletModel->updateBalance($wallet->id, -$amount);
            
            if (!$deducted) {
                $this->rollback();
                error_log("Failed to deduct balance during mining power purchase. User: {$userId}, Amount: {$amount}, Currency: {$currency}");
                return false;
            }
            
            // Create transaction record
            $transactionId = $transactionModel->createTransaction([
                'user_id' => $userId,
                'type' => Transaction::TYPE_PURCHASE,
                'amount' => $amount,
                'fee' => 0,
                'currency' => $currency,
                'status' => Transaction::STATUS_COMPLETED,
                'description' => "Purchase of {$package->name} mining package",
                'reference_id' => $referenceId,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            if (!$transactionId) {
                $this->rollback();
                error_log("Failed to create transaction during mining power purchase. User: {$userId}, Amount: {$amount}, Currency: {$currency}");
                return false;
            }
            
            // Create mining power record
            $miningPowerId = $this->create([
                'user_id' => $userId,
                'package_id' => $packageId,
                'amount' => $amount,
                'status' => self::STATUS_ACTIVE,
                'mining_power' => $package->mining_power,
                'daily_reward_rate' => $package->daily_reward_rate,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'currency' => $currency,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            if (!$miningPowerId) {
                $this->rollback();
                error_log("Failed to create mining power record. User: {$userId}, Package: {$packageId}");
                return false;
            }
            
            // Update user's total mining power
            $userMiningPowerModel = new UserMiningPower();
            $updated = $userMiningPowerModel->increaseMiningPower($userId, $package->mining_power);
            
            if (!$updated) {
                $this->rollback();
                error_log("Failed to update user mining power. User: {$userId}, Mining Power: {$package->mining_power}");
                return false;
            }
            
            // Commit transaction
            $this->commit();
            
            // Create notification
            $notificationModel = new Notification();
            $notificationModel->createNotification(
                $userId,
                Notification::TYPE_MINING,
                'Mining Power Purchased',
                "You have successfully purchased {$package->name} mining package with {$package->mining_power} mining power.",
                '/mining/packages',
                [
                    'package_id' => $packageId,
                    'mining_power' => $package->mining_power,
                    'amount' => $amount,
                    'currency' => $currency
                ]
            );
            
            // Process referral commission if applicable
            $userModel = new User();
            $user = $userModel->find($userId);
            
            if ($user && $user->referred_by) {
                $referralModel = new ReferralCommission();
                $referralModel->createCommission(
                    $user->referred_by,
                    $userId,
                    $transactionId,
                    $amount,
                    $currency,
                    ReferralCommission::TYPE_PURCHASE
                );
            }
            
            return $miningPowerId;
        } catch (\Exception $e) {
            $this->rollback();
            error_log("Exception during mining power purchase: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get mining powers for a user
     *
     * @param int $userId User ID
     * @param string|null $status Optional status filter
     * @param int|null $limit Optional limit
     * @param int|null $offset Optional offset
     * @return array Mining powers
     */
    public function getUserMiningPowers($userId, $status = null, $limit = null, $offset = null) {
        $sql = "SELECT mp.*, mp.id as mining_power_id, pkg.name as package_name
                FROM {$this->table} mp
                LEFT JOIN mining_packages pkg ON mp.package_id = pkg.id
                WHERE mp.user_id = :user_id";
        
        $params = ['user_id' => $userId];
        
        if ($status !== null) {
            $sql .= " AND mp.status = :status";
            $params['status'] = $status;
        }
        
        $sql .= " ORDER BY mp.created_at DESC";
        
        if ($limit !== null) {
            $sql .= " LIMIT :limit";
            $params['limit'] = (int) $limit;
            
            if ($offset !== null) {
                $sql .= " OFFSET :offset";
                $params['offset'] = (int) $offset;
            }
        }
        
        return $this->raw($sql, $params);
    }
    
    /**
     * Get active mining powers
     *
     * @param string|null $currency Optional currency filter
     * @return array Active mining powers
     */
    public function getActiveMiningPowers($currency = null) {
        $sql = "SELECT mp.*, u.username, pkg.name as package_name
                FROM {$this->table} mp
                LEFT JOIN users u ON mp.user_id = u.id
                LEFT JOIN mining_packages pkg ON mp.package_id = pkg.id
                WHERE mp.status = :status";
        
        $params = ['status' => self::STATUS_ACTIVE];
        
        if ($currency !== null) {
            $sql .= " AND mp.currency = :currency";
            $params['currency'] = $currency;
        }
        
        $sql .= " ORDER BY mp.created_at DESC";
        
        return $this->raw($sql, $params);
    }
    
    /**
     * Get mining power details
     *
     * @param int $id Mining power ID
     * @param int|null $userId Optional user ID for security check
     * @return object|null Mining power details or null if not found or not owned by user
     */
    public function getMiningPowerDetails($id, $userId = null) {
        $sql = "SELECT mp.*, pkg.name as package_name, pkg.description, pkg.image
                FROM {$this->table} mp
                LEFT JOIN mining_packages pkg ON mp.package_id = pkg.id
                WHERE mp.id = :id";
        
        $params = ['id' => $id];
        
        if ($userId !== null) {
            $sql .= " AND mp.user_id = :user_id";
            $params['user_id'] = $userId;
        }
        
        $result = $this->raw($sql, $params);
        return $result[0] ?? null;
    }
    
    /**
     * Update mining power status
     *
     * @param int $id Mining power ID
     * @param string $status New status
     * @return bool Success or failure
     */
    public function updateStatus($id, $status) {
        return $this->update($id, [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Check for expired mining powers and update their status
     *
     * @return array Updated mining powers
     */
    public function checkExpiredMiningPowers() {
        $currentDate = date('Y-m-d H:i:s');
        
        $sql = "SELECT mp.*, u.id as user_id, u.username, pkg.name as package_name, mp.mining_power
                FROM {$this->table} mp
                LEFT JOIN users u ON mp.user_id = u.id
                LEFT JOIN mining_packages pkg ON mp.package_id = pkg.id
                WHERE mp.status = :status AND mp.end_date <= :current_date";
        
        $expiredPowers = $this->raw($sql, [
            'status' => self::STATUS_ACTIVE,
            'current_date' => $currentDate
        ]);
        
        if (empty($expiredPowers)) {
            return [];
        }
        
        $updatedPowers = [];
        $userMiningPowerModel = new UserMiningPower();
        $notificationModel = new Notification();
        
        foreach ($expiredPowers as $power) {
            // Update status to expired
            if ($this->updateStatus($power->id, self::STATUS_EXPIRED)) {
                // Reduce user's mining power
                $userMiningPowerModel->decreaseMiningPower($power->user_id, $power->mining_power);
                
                // Send notification to user
                $notificationModel->createNotification(
                    $power->user_id,
                    Notification::TYPE_MINING,
                    'Mining Power Expired',
                    "Your {$power->package_name} mining package with {$power->mining_power} mining power has expired.",
                    '/mining/history',
                    [
                        'mining_power_id' => $power->id,
                        'package_name' => $power->package_name,
                        'mining_power' => $power->mining_power
                    ]
                );
                
                $updatedPowers[] = $power;
            }
        }
        
        return $updatedPowers;
    }
    
    /**
     * Get total active mining power across all users
     *
     * @return float Total active mining power
     */
    public function getTotalActiveMiningPower() {
        $sql = "SELECT IFNULL(SUM(mining_power), 0) as total_power 
                FROM {$this->table} 
                WHERE status = :status";
                
        $result = $this->raw($sql, ['status' => self::STATUS_ACTIVE]);
        return $result[0]->total_power ?? 0;
    }
    
    /**
     * Get active mining power for a user
     *
     * @param int $userId User ID
     * @return float User's active mining power
     */
    public function getUserActiveMiningPower($userId) {
        $sql = "SELECT IFNULL(SUM(mining_power), 0) as total_power 
                FROM {$this->table} 
                WHERE user_id = :user_id AND status = :status";
                
        $result = $this->raw($sql, [
            'user_id' => $userId,
            'status' => self::STATUS_ACTIVE
        ]);
        
        return $result[0]->total_power ?? 0;
    }
    
    /**
     * Get mining power statistics
     *
     * @return array Mining power statistics
     */
    public function getMiningPowerStats() {
        $sql = "SELECT 
                COUNT(*) as total_purchases,
                COUNT(CASE WHEN status = :active THEN 1 END) as active_packages,
                COUNT(CASE WHEN status = :expired THEN 1 END) as expired_packages,
                IFNULL(SUM(CASE WHEN status = :active2 THEN mining_power END), 0) as total_active_power,
                IFNULL(SUM(amount), 0) as total_spent,
                COUNT(DISTINCT user_id) as unique_users
                FROM {$this->table}";
        
        $result = $this->raw($sql, [
            'active' => self::STATUS_ACTIVE,
            'active2' => self::STATUS_ACTIVE,
            'expired' => self::STATUS_EXPIRED
        ]);
        
        return $result[0] ?? [
            'total_purchases' => 0,
            'active_packages' => 0,
            'expired_packages' => 0,
            'total_active_power' => 0,
            'total_spent' => 0,
            'unique_users' => 0
        ];
    }
    
    /**
     * Get mining power statistics for a user
     *
     * @param int $userId User ID
     * @return array User's mining power statistics
     */
    public function getUserMiningPowerStats($userId) {
        $sql = "SELECT 
                COUNT(*) as total_purchases,
                COUNT(CASE WHEN status = :active THEN 1 END) as active_packages,
                COUNT(CASE WHEN status = :expired THEN 1 END) as expired_packages,
                IFNULL(SUM(CASE WHEN status = :active2 THEN mining_power END), 0) as active_power,
                IFNULL(SUM(amount), 0) as total_spent
                FROM {$this->table}
                WHERE user_id = :user_id";
        
        $result = $this->raw($sql, [
            'user_id' => $userId,
            'active' => self::STATUS_ACTIVE,
            'active2' => self::STATUS_ACTIVE,
            'expired' => self::STATUS_EXPIRED
        ]);
        
        return $result[0] ?? [
            'total_purchases' => 0,
            'active_packages' => 0,
            'expired_packages' => 0,
            'active_power' => 0,
            'total_spent' => 0
        ];
    }
    
    /**
     * Get recent mining power purchases
     *
     * @param int $limit Limit
     * @return array Recent purchases
     */
    public function getRecentPurchases($limit = 5) {
        $sql = "SELECT mp.*, u.username, pkg.name as package_name
                FROM {$this->table} mp
                LEFT JOIN users u ON mp.user_id = u.id
                LEFT JOIN mining_packages pkg ON mp.package_id = pkg.id
                ORDER BY mp.created_at DESC
                LIMIT :limit";
        
        return $this->raw($sql, ['limit' => (int) $limit]);
    }
    
    /**
     * Calculate daily reward for a mining power
     *
     * @param int $miningPowerId Mining power ID
     * @return float Daily reward amount
     */
    public function calculateDailyReward($miningPowerId) {
        $miningPower = $this->find($miningPowerId);
        
        if (!$miningPower || $miningPower->status !== self::STATUS_ACTIVE) {
            return 0;
        }
        
        // Calculate daily reward based on mining power and daily reward rate
        $dailyReward = $miningPower->mining_power * ($miningPower->daily_reward_rate / 100);
        
        return $dailyReward;
    }
    
    /**
     * Calculate remaining days for a mining power
     *
     * @param int $miningPowerId Mining power ID
     * @return int Remaining days
     */
    public function calculateRemainingDays($miningPowerId) {
        $miningPower = $this->find($miningPowerId);
        
        if (!$miningPower || $miningPower->status !== self::STATUS_ACTIVE) {
            return 0;
        }
        
        $currentDate = time();
        $endDate = strtotime($miningPower->end_date);
        
        $remainingSeconds = max(0, $endDate - $currentDate);
        $remainingDays = ceil($remainingSeconds / (60 * 60 * 24));
        
        return $remainingDays;
    }
} 