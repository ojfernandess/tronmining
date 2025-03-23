<?php

namespace App\Models;

use App\Core\Model;

/**
 * ActivityLog Model
 * 
 * Handles activity logging for user actions
 */
class ActivityLog extends Model
{
    protected $table = 'activity_logs';
    
    protected $fillable = [
        'user_id', 'action', 'entity_type', 'entity_id', 
        'description', 'ip_address', 'user_agent', 'metadata'
    ];
    
    // Action types
    const ACTION_LOGIN = 'login';
    const ACTION_LOGOUT = 'logout';
    const ACTION_REGISTER = 'register';
    const ACTION_PURCHASE = 'purchase';
    const ACTION_WITHDRAWAL = 'withdrawal';
    const ACTION_DEPOSIT = 'deposit';
    const ACTION_UPDATE_PROFILE = 'update_profile';
    const ACTION_MINING_REWARD = 'mining_reward';
    const ACTION_REFERRAL_COMMISSION = 'referral_commission';
    
    /**
     * Log a user activity
     *
     * @param int $userId User ID
     * @param string $action Action type
     * @param string $description Description
     * @param string $entityType Entity type (optional)
     * @param int $entityId Entity ID (optional)
     * @param array $metadata Additional data (optional)
     * @return int|bool ID of the created log or false on failure
     */
    public function logActivity($userId, $action, $description, $entityType = null, $entityId = null, $metadata = [])
    {
        $data = [
            'user_id' => $userId,
            'action' => $action,
            'description' => $description,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'ip_address' => $this->getIpAddress(),
            'user_agent' => $this->getUserAgent(),
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        if (!empty($metadata)) {
            $data['metadata'] = json_encode($metadata);
        }
        
        return $this->create($data);
    }
    
    /**
     * Get user activities
     *
     * @param int $userId User ID
     * @param int $limit Number of activities to get
     * @param int $offset Offset
     * @return array User activities
     */
    public function getUserActivities($userId, $limit = 10, $offset = 0)
    {
        $query = "SELECT * FROM {$this->table} 
                 WHERE user_id = :user_id 
                 ORDER BY created_at DESC 
                 LIMIT :limit OFFSET :offset";
        
        return $this->raw($query, [
            'user_id' => $userId,
            'limit' => $limit,
            'offset' => $offset
        ]);
    }
    
    /**
     * Get activities by action type
     *
     * @param string $action Action type
     * @param int $limit Number of activities to get
     * @param int $offset Offset
     * @return array Activities
     */
    public function getActivitiesByAction($action, $limit = 10, $offset = 0)
    {
        $query = "SELECT * FROM {$this->table} 
                 WHERE action = :action 
                 ORDER BY created_at DESC 
                 LIMIT :limit OFFSET :offset";
        
        return $this->raw($query, [
            'action' => $action,
            'limit' => $limit,
            'offset' => $offset
        ]);
    }
    
    /**
     * Get activities related to an entity
     *
     * @param string $entityType Entity type
     * @param int $entityId Entity ID
     * @param int $limit Number of activities to get
     * @param int $offset Offset
     * @return array Activities
     */
    public function getEntityActivities($entityType, $entityId, $limit = 10, $offset = 0)
    {
        $query = "SELECT * FROM {$this->table} 
                 WHERE entity_type = :entity_type AND entity_id = :entity_id 
                 ORDER BY created_at DESC 
                 LIMIT :limit OFFSET :offset";
        
        return $this->raw($query, [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'limit' => $limit,
            'offset' => $offset
        ]);
    }
    
    /**
     * Count user activities
     *
     * @param int $userId User ID
     * @return int Number of activities
     */
    public function countUserActivities($userId)
    {
        $query = "SELECT COUNT(*) as count FROM {$this->table} WHERE user_id = :user_id";
        $result = $this->raw($query, ['user_id' => $userId], false);
        
        return $result ? $result->count : 0;
    }
    
    /**
     * Delete old activities
     *
     * @param int $days Number of days to keep
     * @return bool Success or failure
     */
    public function deleteOldActivities($days = 30)
    {
        $date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $query = "DELETE FROM {$this->table} WHERE created_at < :date";
        
        return $this->db->query($query)
            ->bind(['date' => $date])
            ->execute();
    }
    
    /**
     * Get the client IP address
     *
     * @return string IP address
     */
    protected function getIpAddress()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }
        
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Get the user agent
     *
     * @return string User agent
     */
    protected function getUserAgent()
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }
    
    /**
     * Get a readable label for action types
     *
     * @param string $action Action type
     * @return string Action label
     */
    public static function getActionLabel($action)
    {
        $labels = [
            self::ACTION_LOGIN => 'Login',
            self::ACTION_LOGOUT => 'Logout',
            self::ACTION_REGISTER => 'Registration',
            self::ACTION_PURCHASE => 'Package Purchase',
            self::ACTION_WITHDRAWAL => 'Withdrawal',
            self::ACTION_DEPOSIT => 'Deposit',
            self::ACTION_UPDATE_PROFILE => 'Profile Update',
            self::ACTION_MINING_REWARD => 'Mining Reward',
            self::ACTION_REFERRAL_COMMISSION => 'Referral Commission'
        ];
        
        return $labels[$action] ?? 'Unknown Action';
    }
    
    /**
     * Get recent activities for all users (admin panel)
     *
     * @param int $limit Number of activities to get
     * @param int $offset Offset
     * @return array Activities
     */
    public function getRecentActivities($limit = 20, $offset = 0)
    {
        $query = "SELECT a.*, u.username 
                 FROM {$this->table} a
                 LEFT JOIN users u ON a.user_id = u.id
                 ORDER BY a.created_at DESC 
                 LIMIT :limit OFFSET :offset";
        
        return $this->raw($query, [
            'limit' => $limit,
            'offset' => $offset
        ]);
    }
    
    /**
     * Search activities
     *
     * @param array $filters Search filters (user_id, action, entity_type, date_from, date_to)
     * @param int $limit Number of activities to get
     * @param int $offset Offset
     * @return array Activities
     */
    public function searchActivities($filters = [], $limit = 20, $offset = 0)
    {
        $query = "SELECT a.*, u.username 
                 FROM {$this->table} a
                 LEFT JOIN users u ON a.user_id = u.id
                 WHERE 1=1";
        
        $params = [];
        
        if (isset($filters['user_id'])) {
            $query .= " AND a.user_id = :user_id";
            $params['user_id'] = $filters['user_id'];
        }
        
        if (isset($filters['action'])) {
            $query .= " AND a.action = :action";
            $params['action'] = $filters['action'];
        }
        
        if (isset($filters['entity_type'])) {
            $query .= " AND a.entity_type = :entity_type";
            $params['entity_type'] = $filters['entity_type'];
        }
        
        if (isset($filters['date_from'])) {
            $query .= " AND a.created_at >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }
        
        if (isset($filters['date_to'])) {
            $query .= " AND a.created_at <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }
        
        $query .= " ORDER BY a.created_at DESC LIMIT :limit OFFSET :offset";
        
        $params['limit'] = $limit;
        $params['offset'] = $offset;
        
        return $this->raw($query, $params);
    }
} 