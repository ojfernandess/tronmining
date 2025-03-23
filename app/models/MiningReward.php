<?php
namespace App\Models;

use App\Core\Model;

/**
 * MiningReward Model
 * 
 * Handles mining rewards for users
 */
class MiningReward extends Model {
    // Table name
    protected $table = 'mining_rewards';
    
    // Fields that can be mass assigned
    protected $fillable = [
        'user_id', 'amount', 'mining_power', 'reward_date', 
        'status', 'transaction_id', 'processed_at'
    ];
    
    // Reward statuses
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    
    /**
     * Calculate and create mining rewards for a user
     *
     * @param int $userId User ID
     * @param float $miningPower User's mining power
     * @param string $date Reward date (YYYY-MM-DD)
     * @return int|bool ID of created reward or false on failure
     */
    public function createReward($userId, $miningPower, $date = null) {
        if ($date === null) {
            $date = date('Y-m-d');
        }
        
        // Check if reward for this date already exists
        if ($this->hasDailyReward($userId, $date)) {
            return false;
        }
        
        // Calculate reward amount based on mining power
        $amount = $this->calculateRewardAmount($miningPower);
        
        // Create reward record
        return $this->create([
            'user_id' => $userId,
            'amount' => $amount,
            'mining_power' => $miningPower,
            'reward_date' => $date,
            'status' => self::STATUS_PENDING,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Check if user already has a reward for the given date
     *
     * @param int $userId User ID
     * @param string $date Date (YYYY-MM-DD)
     * @return bool Whether reward exists
     */
    public function hasDailyReward($userId, $date) {
        $query = "SELECT COUNT(*) as count FROM {$this->table} 
                 WHERE user_id = :user_id AND reward_date = :date";
        
        $result = $this->raw($query, [
            'user_id' => $userId,
            'date' => $date
        ], false);
        
        return $result && $result->count > 0;
    }
    
    /**
     * Calculate reward amount based on mining power
     *
     * @param float $miningPower Mining power
     * @return float Reward amount
     */
    public function calculateRewardAmount($miningPower) {
        // Get reward rate from settings
        $settingModel = new Setting();
        $rewardRate = $settingModel->get('mining_reward_rate', 0.001);
        
        // Calculate reward based on mining power and rate
        return round($miningPower * $rewardRate, 8);
    }
    
    /**
     * Get user's rewards
     *
     * @param int $userId User ID
     * @param int $limit Limit
     * @param int $offset Offset
     * @return array User's rewards
     */
    public function getUserRewards($userId, $limit = 20, $offset = 0) {
        $query = "SELECT * FROM {$this->table} 
                 WHERE user_id = :user_id 
                 ORDER BY reward_date DESC 
                 LIMIT :limit OFFSET :offset";
        
        return $this->raw($query, [
            'user_id' => $userId,
            'limit' => $limit,
            'offset' => $offset
        ]);
    }
    
    /**
     * Get user's total rewards
     *
     * @param int $userId User ID
     * @param string $status Optional status filter
     * @return float Total rewards
     */
    public function getUserTotalRewards($userId, $status = null) {
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
     * Get users' total mining rewards for the current day
     *
     * @param string $date Specific date (YYYY-MM-DD) or null for today
     * @return float Total rewards
     */
    public function getTotalDailyRewards($date = null) {
        if ($date === null) {
            $date = date('Y-m-d');
        }
        
        $query = "SELECT SUM(amount) as total FROM {$this->table} 
                 WHERE reward_date = :date";
        
        $result = $this->raw($query, ['date' => $date], false);
        
        return $result ? floatval($result->total) : 0;
    }
    
    /**
     * Get pending rewards that need to be processed
     *
     * @param int $limit Limit
     * @return array Pending rewards
     */
    public function getPendingRewards($limit = 100) {
        $query = "SELECT r.*, u.username, u.wallet_address 
                 FROM {$this->table} r
                 JOIN users u ON r.user_id = u.id
                 WHERE r.status = :status
                 ORDER BY r.reward_date ASC
                 LIMIT :limit";
        
        return $this->raw($query, [
            'status' => self::STATUS_PENDING,
            'limit' => $limit
        ]);
    }
    
    /**
     * Mark rewards as processing
     *
     * @param array $rewardIds Array of reward IDs
     * @return bool Success or failure
     */
    public function markAsProcessing($rewardIds) {
        if (empty($rewardIds)) {
            return false;
        }
        
        $placeholders = implode(',', array_fill(0, count($rewardIds), '?'));
        
        $query = "UPDATE {$this->table} 
                 SET status = ?, updated_at = ? 
                 WHERE id IN ({$placeholders})";
        
        $params = array_merge([self::STATUS_PROCESSING, date('Y-m-d H:i:s')], $rewardIds);
        
        return $this->execute($query, $params);
    }
    
    /**
     * Complete reward processing
     *
     * @param int $rewardId Reward ID
     * @param string $transactionId Blockchain transaction ID
     * @return bool Success or failure
     */
    public function completeReward($rewardId, $transactionId) {
        return $this->update($rewardId, [
            'status' => self::STATUS_COMPLETED,
            'transaction_id' => $transactionId,
            'processed_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Mark reward as failed
     *
     * @param int $rewardId Reward ID
     * @param string $reason Failure reason
     * @return bool Success or failure
     */
    public function failReward($rewardId, $reason = null) {
        $data = [
            'status' => self::STATUS_FAILED,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        if ($reason) {
            $data['notes'] = $reason;
        }
        
        return $this->update($rewardId, $data);
    }
    
    /**
     * Generate daily rewards for all active users
     *
     * @param string $date Date to generate rewards for (YYYY-MM-DD)
     * @return array Result statistics
     */
    public function generateDailyRewards($date = null) {
        if ($date === null) {
            $date = date('Y-m-d');
        }
        
        $stats = [
            'processed' => 0,
            'skipped' => 0,
            'failed' => 0,
            'total_amount' => 0
        ];
        
        // Get active users with mining power
        $userMiningPowerModel = new UserMiningPower();
        $activeMiningPowers = $userMiningPowerModel->getActiveMiningPowers();
        
        foreach ($activeMiningPowers as $userPower) {
            // Skip users without mining power
            if ($userPower->mining_power <= 0) {
                $stats['skipped']++;
                continue;
            }
            
            // Check if already has reward for this date
            if ($this->hasDailyReward($userPower->user_id, $date)) {
                $stats['skipped']++;
                continue;
            }
            
            // Calculate and create reward
            $amount = $this->calculateRewardAmount($userPower->mining_power);
            $rewardId = $this->create([
                'user_id' => $userPower->user_id,
                'amount' => $amount,
                'mining_power' => $userPower->mining_power,
                'reward_date' => $date,
                'status' => self::STATUS_PENDING,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            if ($rewardId) {
                $stats['processed']++;
                $stats['total_amount'] += $amount;
            } else {
                $stats['failed']++;
            }
        }
        
        return $stats;
    }
    
    /**
     * Get reward statistics
     *
     * @return array Statistics
     */
    public function getRewardStats() {
        $stats = [
            'total_rewards' => 0,
            'pending_rewards' => 0,
            'completed_rewards' => 0,
            'daily_rewards' => 0,
            'monthly_rewards' => 0,
            'user_count' => 0
        ];
        
        // Total rewards
        $query = "SELECT SUM(amount) as total FROM {$this->table}";
        $result = $this->raw($query, [], false);
        $stats['total_rewards'] = $result ? floatval($result->total) : 0;
        
        // Pending rewards
        $query = "SELECT SUM(amount) as total FROM {$this->table} WHERE status = :status";
        $result = $this->raw($query, ['status' => self::STATUS_PENDING], false);
        $stats['pending_rewards'] = $result ? floatval($result->total) : 0;
        
        // Completed rewards
        $query = "SELECT SUM(amount) as total FROM {$this->table} WHERE status = :status";
        $result = $this->raw($query, ['status' => self::STATUS_COMPLETED], false);
        $stats['completed_rewards'] = $result ? floatval($result->total) : 0;
        
        // Daily rewards (today)
        $today = date('Y-m-d');
        $query = "SELECT SUM(amount) as total FROM {$this->table} WHERE reward_date = :date";
        $result = $this->raw($query, ['date' => $today], false);
        $stats['daily_rewards'] = $result ? floatval($result->total) : 0;
        
        // Monthly rewards (current month)
        $startOfMonth = date('Y-m-01');
        $endOfMonth = date('Y-m-t');
        $query = "SELECT SUM(amount) as total FROM {$this->table} 
                 WHERE reward_date BETWEEN :start AND :end";
        $result = $this->raw($query, [
            'start' => $startOfMonth,
            'end' => $endOfMonth
        ], false);
        $stats['monthly_rewards'] = $result ? floatval($result->total) : 0;
        
        // User count (users who received rewards)
        $query = "SELECT COUNT(DISTINCT user_id) as count FROM {$this->table}";
        $result = $this->raw($query, [], false);
        $stats['user_count'] = $result ? intval($result->count) : 0;
        
        return $stats;
    }
    
    /**
     * Search rewards
     *
     * @param array $filters Search filters
     * @param int $limit Limit
     * @param int $offset Offset
     * @return array Rewards
     */
    public function searchRewards($filters = [], $limit = 20, $offset = 0) {
        $query = "SELECT r.*, u.username 
                 FROM {$this->table} r
                 JOIN users u ON r.user_id = u.id
                 WHERE 1=1";
        
        $params = [];
        
        if (isset($filters['user_id'])) {
            $query .= " AND r.user_id = :user_id";
            $params['user_id'] = $filters['user_id'];
        }
        
        if (isset($filters['username'])) {
            $query .= " AND u.username LIKE :username";
            $params['username'] = '%' . $filters['username'] . '%';
        }
        
        if (isset($filters['status'])) {
            $query .= " AND r.status = :status";
            $params['status'] = $filters['status'];
        }
        
        if (isset($filters['date_from'])) {
            $query .= " AND r.reward_date >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }
        
        if (isset($filters['date_to'])) {
            $query .= " AND r.reward_date <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }
        
        if (isset($filters['min_amount'])) {
            $query .= " AND r.amount >= :min_amount";
            $params['min_amount'] = $filters['min_amount'];
        }
        
        if (isset($filters['max_amount'])) {
            $query .= " AND r.amount <= :max_amount";
            $params['max_amount'] = $filters['max_amount'];
        }
        
        $query .= " ORDER BY r.reward_date DESC LIMIT :limit OFFSET :offset";
        
        $params['limit'] = $limit;
        $params['offset'] = $offset;
        
        return $this->raw($query, $params);
    }
    
    /**
     * Get a readable label for statuses
     *
     * @param string $status Status
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