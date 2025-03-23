<?php
namespace App\Models;

use App\Core\Model;

/**
 * UserMiningPower Model
 * 
 * Handles user mining power management
 */
class UserMiningPower extends Model {
    // Table name
    protected $table = 'user_mining_power';
    
    // Fields that can be mass assigned
    protected $fillable = [
        'user_id', 'package_id', 'mining_power', 'price_paid',
        'start_date', 'end_date', 'status', 'transaction_id'
    ];
    
    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_ACTIVE = 'active';
    const STATUS_EXPIRED = 'expired';
    const STATUS_CANCELLED = 'cancelled';
    
    /**
     * Add mining power to a user
     *
     * @param int $userId User ID
     * @param int $packageId Mining package ID
     * @param float $miningPower Mining power amount
     * @param float $pricePaid Price paid
     * @param string $transactionId Transaction ID
     * @param int $durationDays Duration in days (null for lifetime)
     * @return int|bool ID of created mining power or false on failure
     */
    public function addMiningPower($userId, $packageId, $miningPower, $pricePaid, $transactionId = null, $durationDays = null) {
        $startDate = date('Y-m-d');
        $endDate = null;
        
        if ($durationDays !== null) {
            $endDate = date('Y-m-d', strtotime("+{$durationDays} days"));
        }
        
        return $this->create([
            'user_id' => $userId,
            'package_id' => $packageId,
            'mining_power' => $miningPower,
            'price_paid' => $pricePaid,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => self::STATUS_ACTIVE,
            'transaction_id' => $transactionId,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Get user's total active mining power
     *
     * @param int $userId User ID
     * @return float Total mining power
     */
    public function getUserTotalMiningPower($userId) {
        $query = "SELECT SUM(mining_power) as total FROM {$this->table} 
                 WHERE user_id = :user_id AND status = :status 
                 AND (end_date IS NULL OR end_date >= :current_date)";
        
        $result = $this->raw($query, [
            'user_id' => $userId,
            'status' => self::STATUS_ACTIVE,
            'current_date' => date('Y-m-d')
        ], false);
        
        return $result ? floatval($result->total) : 0;
    }
    
    /**
     * Get user's active mining powers
     *
     * @param int $userId User ID
     * @return array Active mining powers
     */
    public function getUserActiveMiningPowers($userId) {
        $query = "SELECT ump.*, mp.name as package_name 
                 FROM {$this->table} ump
                 LEFT JOIN mining_packages mp ON ump.package_id = mp.id
                 WHERE ump.user_id = :user_id 
                 AND ump.status = :status 
                 AND (ump.end_date IS NULL OR ump.end_date >= :current_date)
                 ORDER BY ump.created_at DESC";
        
        return $this->raw($query, [
            'user_id' => $userId,
            'status' => self::STATUS_ACTIVE,
            'current_date' => date('Y-m-d')
        ]);
    }
    
    /**
     * Get user's mining powers history
     *
     * @param int $userId User ID
     * @param int $limit Limit
     * @param int $offset Offset
     * @return array Mining powers history
     */
    public function getUserMiningPowerHistory($userId, $limit = 20, $offset = 0) {
        $query = "SELECT ump.*, mp.name as package_name 
                 FROM {$this->table} ump
                 LEFT JOIN mining_packages mp ON ump.package_id = mp.id
                 WHERE ump.user_id = :user_id 
                 ORDER BY ump.created_at DESC
                 LIMIT :limit OFFSET :offset";
        
        return $this->raw($query, [
            'user_id' => $userId,
            'limit' => $limit,
            'offset' => $offset
        ]);
    }
    
    /**
     * Get all active mining powers
     *
     * @return array Active mining powers
     */
    public function getActiveMiningPowers() {
        $query = "SELECT ump.*, u.username 
                 FROM {$this->table} ump
                 JOIN users u ON ump.user_id = u.id
                 WHERE ump.status = :status 
                 AND (ump.end_date IS NULL OR ump.end_date >= :current_date)
                 ORDER BY ump.user_id ASC, ump.created_at DESC";
        
        return $this->raw($query, [
            'status' => self::STATUS_ACTIVE,
            'current_date' => date('Y-m-d')
        ]);
    }
    
    /**
     * Get mining powers expiring soon
     *
     * @param int $daysThreshold Days threshold
     * @return array Mining powers expiring soon
     */
    public function getMiningPowersExpiringSoon($daysThreshold = 7) {
        $currentDate = date('Y-m-d');
        $expiryThreshold = date('Y-m-d', strtotime("+{$daysThreshold} days"));
        
        $query = "SELECT ump.*, u.username, u.email, mp.name as package_name 
                 FROM {$this->table} ump
                 JOIN users u ON ump.user_id = u.id
                 LEFT JOIN mining_packages mp ON ump.package_id = mp.id
                 WHERE ump.status = :status 
                 AND ump.end_date IS NOT NULL
                 AND ump.end_date BETWEEN :current_date AND :expiry_threshold
                 ORDER BY ump.end_date ASC";
        
        return $this->raw($query, [
            'status' => self::STATUS_ACTIVE,
            'current_date' => $currentDate,
            'expiry_threshold' => $expiryThreshold
        ]);
    }
    
    /**
     * Update expired mining powers
     *
     * @return int Number of updated records
     */
    public function updateExpiredMiningPowers() {
        $query = "UPDATE {$this->table} 
                 SET status = :new_status, updated_at = :updated_at
                 WHERE status = :current_status 
                 AND end_date IS NOT NULL 
                 AND end_date < :current_date";
        
        $params = [
            'new_status' => self::STATUS_EXPIRED,
            'updated_at' => date('Y-m-d H:i:s'),
            'current_status' => self::STATUS_ACTIVE,
            'current_date' => date('Y-m-d')
        ];
        
        $this->execute($query, $params);
        
        return $this->affectedRows();
    }
    
    /**
     * Renew mining power
     *
     * @param int $id Mining power ID
     * @param int $durationDays Duration in days
     * @param float $pricePaid Price paid
     * @param string $transactionId Transaction ID
     * @return bool Success or failure
     */
    public function renewMiningPower($id, $durationDays, $pricePaid, $transactionId = null) {
        $miningPower = $this->find($id);
        
        if (!$miningPower) {
            return false;
        }
        
        // Calculate new end date
        $currentEndDate = $miningPower->end_date;
        
        if ($currentEndDate && strtotime($currentEndDate) < time()) {
            // If already expired, start from today
            $startDate = date('Y-m-d');
        } else {
            // If not expired, extend from current end date
            $startDate = $currentEndDate;
        }
        
        $endDate = date('Y-m-d', strtotime($startDate . " +{$durationDays} days"));
        
        return $this->update($id, [
            'end_date' => $endDate,
            'status' => self::STATUS_ACTIVE,
            'price_paid' => $miningPower->price_paid + $pricePaid,
            'transaction_id' => $transactionId,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Cancel mining power
     *
     * @param int $id Mining power ID
     * @param string $reason Cancellation reason
     * @return bool Success or failure
     */
    public function cancelMiningPower($id, $reason = null) {
        $data = [
            'status' => self::STATUS_CANCELLED,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        if ($reason) {
            $data['notes'] = $reason;
        }
        
        return $this->update($id, $data);
    }
    
    /**
     * Increase mining power
     *
     * @param int $id Mining power ID
     * @param float $additionalPower Additional mining power
     * @param float $pricePaid Additional price paid
     * @param string $transactionId Transaction ID
     * @return bool Success or failure
     */
    public function increaseMiningPower($id, $additionalPower, $pricePaid, $transactionId = null) {
        $miningPower = $this->find($id);
        
        if (!$miningPower) {
            return false;
        }
        
        return $this->update($id, [
            'mining_power' => $miningPower->mining_power + $additionalPower,
            'price_paid' => $miningPower->price_paid + $pricePaid,
            'transaction_id' => $transactionId,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Get mining power statistics
     *
     * @return array Statistics
     */
    public function getMiningPowerStats() {
        $stats = [
            'total_mining_power' => 0,
            'active_mining_power' => 0,
            'total_users' => 0,
            'active_users' => 0,
            'total_spent' => 0,
            'by_package' => []
        ];
        
        // Total mining power (all time)
        $query = "SELECT SUM(mining_power) as total FROM {$this->table}";
        $result = $this->raw($query, [], false);
        $stats['total_mining_power'] = $result ? floatval($result->total) : 0;
        
        // Active mining power
        $query = "SELECT SUM(mining_power) as total FROM {$this->table} 
                 WHERE status = :status AND (end_date IS NULL OR end_date >= :current_date)";
        $result = $this->raw($query, [
            'status' => self::STATUS_ACTIVE,
            'current_date' => date('Y-m-d')
        ], false);
        $stats['active_mining_power'] = $result ? floatval($result->total) : 0;
        
        // Total users who have purchased mining power
        $query = "SELECT COUNT(DISTINCT user_id) as count FROM {$this->table}";
        $result = $this->raw($query, [], false);
        $stats['total_users'] = $result ? intval($result->count) : 0;
        
        // Active users with mining power
        $query = "SELECT COUNT(DISTINCT user_id) as count FROM {$this->table} 
                 WHERE status = :status AND (end_date IS NULL OR end_date >= :current_date)";
        $result = $this->raw($query, [
            'status' => self::STATUS_ACTIVE,
            'current_date' => date('Y-m-d')
        ], false);
        $stats['active_users'] = $result ? intval($result->count) : 0;
        
        // Total spent on mining power
        $query = "SELECT SUM(price_paid) as total FROM {$this->table}";
        $result = $this->raw($query, [], false);
        $stats['total_spent'] = $result ? floatval($result->total) : 0;
        
        // Stats by package
        $query = "SELECT mp.id, mp.name, COUNT(*) as count, SUM(ump.mining_power) as total_power, 
                  SUM(ump.price_paid) as total_spent
                 FROM {$this->table} ump
                 JOIN mining_packages mp ON ump.package_id = mp.id
                 GROUP BY mp.id, mp.name";
        $results = $this->raw($query);
        
        foreach ($results as $row) {
            $stats['by_package'][$row->id] = [
                'name' => $row->name,
                'count' => $row->count,
                'total_power' => floatval($row->total_power),
                'total_spent' => floatval($row->total_spent)
            ];
        }
        
        return $stats;
    }
    
    /**
     * Search mining powers
     *
     * @param array $filters Search filters
     * @param int $limit Limit
     * @param int $offset Offset
     * @return array Mining powers
     */
    public function searchMiningPowers($filters = [], $limit = 20, $offset = 0) {
        $query = "SELECT ump.*, u.username, mp.name as package_name 
                 FROM {$this->table} ump
                 JOIN users u ON ump.user_id = u.id
                 LEFT JOIN mining_packages mp ON ump.package_id = mp.id
                 WHERE 1=1";
        
        $params = [];
        
        if (isset($filters['user_id'])) {
            $query .= " AND ump.user_id = :user_id";
            $params['user_id'] = $filters['user_id'];
        }
        
        if (isset($filters['username'])) {
            $query .= " AND u.username LIKE :username";
            $params['username'] = '%' . $filters['username'] . '%';
        }
        
        if (isset($filters['package_id'])) {
            $query .= " AND ump.package_id = :package_id";
            $params['package_id'] = $filters['package_id'];
        }
        
        if (isset($filters['status'])) {
            $query .= " AND ump.status = :status";
            $params['status'] = $filters['status'];
        }
        
        if (isset($filters['min_power'])) {
            $query .= " AND ump.mining_power >= :min_power";
            $params['min_power'] = $filters['min_power'];
        }
        
        if (isset($filters['max_power'])) {
            $query .= " AND ump.mining_power <= :max_power";
            $params['max_power'] = $filters['max_power'];
        }
        
        if (isset($filters['start_date_from'])) {
            $query .= " AND ump.start_date >= :start_date_from";
            $params['start_date_from'] = $filters['start_date_from'];
        }
        
        if (isset($filters['start_date_to'])) {
            $query .= " AND ump.start_date <= :start_date_to";
            $params['start_date_to'] = $filters['start_date_to'];
        }
        
        if (isset($filters['end_date_from'])) {
            $query .= " AND ump.end_date >= :end_date_from";
            $params['end_date_from'] = $filters['end_date_from'];
        }
        
        if (isset($filters['end_date_to'])) {
            $query .= " AND ump.end_date <= :end_date_to";
            $params['end_date_to'] = $filters['end_date_to'];
        }
        
        if (isset($filters['active_only']) && $filters['active_only']) {
            $query .= " AND ump.status = :active_status AND (ump.end_date IS NULL OR ump.end_date >= :current_date)";
            $params['active_status'] = self::STATUS_ACTIVE;
            $params['current_date'] = date('Y-m-d');
        }
        
        $query .= " ORDER BY ump.created_at DESC LIMIT :limit OFFSET :offset";
        
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
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_EXPIRED => 'Expired',
            self::STATUS_CANCELLED => 'Cancelled'
        ];
        
        return $labels[$status] ?? 'Unknown';
    }
} 