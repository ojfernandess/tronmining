<?php
namespace App\Models;

use App\Core\Model;

/**
 * User Model
 * 
 * Handles user-related database operations
 */
class User extends Model {
    // Table name
    protected $table = 'users';
    
    // Fields that can be mass assigned
    protected $fillable = [
        'username', 'email', 'password', 'full_name', 'country', 'phone',
        'referral_code', 'referred_by', 'status', 'role', 'last_login',
        'email_verified', 'kyc_verified', 'tfa_enabled', 'tfa_secret',
        'profile_image'
    ];
    
    // Fields that are hidden from serialization
    protected $hidden = ['password', 'tfa_secret'];
    
    // User roles
    const ROLE_ADMIN = 'admin';
    const ROLE_USER = 'user';
    const ROLE_MANAGER = 'manager';
    
    // User statuses
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_SUSPENDED = 'suspended';
    const STATUS_BANNED = 'banned';
    
    /**
     * Find user by email
     *
     * @param string $email User email
     * @return object|null User data or null if not found
     */
    public function findByEmail($email) {
        return $this->findBy('email', $email);
    }
    
    /**
     * Find user by username
     *
     * @param string $username Username
     * @return object|null User data or null if not found
     */
    public function findByUsername($username) {
        return $this->findBy('username', $username);
    }
    
    /**
     * Find user by referral code
     *
     * @param string $code Referral code
     * @return object|null User data or null if not found
     */
    public function findByReferralCode($code) {
        return $this->findBy('referral_code', $code);
    }
    
    /**
     * Authenticate a user with email/username and password
     *
     * @param string $identifier Email or username
     * @param string $password Password
     * @return object|bool User data or false if authentication fails
     */
    public function authenticate($identifier, $password) {
        // Check if identifier is email or username
        $field = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        
        $user = $this->findBy($field, $identifier);
        
        if (!$user) {
            return false;
        }
        
        // Verify password
        if (!password_verify($password, $user->password)) {
            return false;
        }
        
        // Update last login time
        $this->update($user->id, [
            'last_login' => date('Y-m-d H:i:s')
        ]);
        
        return $user;
    }
    
    /**
     * Create a new user
     *
     * @param array $data User data
     * @return int|bool ID of the created user or false on failure
     */
    public function createUser($data) {
        // Hash password
        if (isset($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        
        // Generate referral code if not provided
        if (!isset($data['referral_code']) || empty($data['referral_code'])) {
            $data['referral_code'] = $this->generateReferralCode();
        }
        
        // Set default role if not provided
        if (!isset($data['role']) || empty($data['role'])) {
            $data['role'] = self::ROLE_USER;
        }
        
        // Set default status if not provided
        if (!isset($data['status']) || empty($data['status'])) {
            $data['status'] = self::STATUS_ACTIVE;
        }
        
        // Set default values for verification fields
        $data['email_verified'] = $data['email_verified'] ?? 0;
        $data['kyc_verified'] = $data['kyc_verified'] ?? 0;
        $data['tfa_enabled'] = $data['tfa_enabled'] ?? 0;
        
        return $this->create($data);
    }
    
    /**
     * Update user information
     *
     * @param int $id User ID
     * @param array $data User data
     * @return bool Success or failure
     */
    public function updateUser($id, $data) {
        // Hash password if provided
        if (isset($data['password']) && !empty($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        } else {
            // Don't update password if not provided
            unset($data['password']);
        }
        
        return $this->update($id, $data);
    }
    
    /**
     * Generate a unique referral code
     *
     * @return string Referral code
     */
    protected function generateReferralCode() {
        $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $code = '';
        
        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= $chars[rand(0, strlen($chars) - 1)];
            }
            
            // Check if code already exists
            $exists = $this->findByReferralCode($code);
        } while ($exists);
        
        return $code;
    }
    
    /**
     * Get user's referrals
     *
     * @param int $userId User ID
     * @return array Referred users
     */
    public function getUserReferrals($userId) {
        return $this->where('referred_by', $userId);
    }
    
    /**
     * Check if user has verified email
     *
     * @param int $userId User ID
     * @return bool Whether email is verified
     */
    public function isEmailVerified($userId) {
        $user = $this->find($userId);
        
        return $user && $user->email_verified == 1;
    }
    
    /**
     * Check if user has verified KYC
     *
     * @param int $userId User ID
     * @return bool Whether KYC is verified
     */
    public function isKycVerified($userId) {
        $user = $this->find($userId);
        
        return $user && $user->kyc_verified == 1;
    }
    
    /**
     * Check if user has two-factor authentication enabled
     *
     * @param int $userId User ID
     * @return bool Whether 2FA is enabled
     */
    public function hasTfaEnabled($userId) {
        $user = $this->find($userId);
        
        return $user && $user->tfa_enabled == 1;
    }
    
    /**
     * Get user statistics
     *
     * @param int $userId User ID
     * @return array User statistics
     */
    public function getUserStats($userId) {
        // Get total mining power
        $miningPowerModel = new UserMiningPower();
        $totalMiningPower = $miningPowerModel->getUserTotalMiningPower($userId);
        
        // Get total mining rewards
        $miningRewardModel = new MiningReward();
        $totalRewards = $miningRewardModel->getUserTotalRewards($userId);
        
        // Get active mining packages
        $activePackages = $miningPowerModel->getUserActiveMiningPower($userId);
        
        // Get referral information
        $referrals = $this->getUserReferrals($userId);
        $referralCount = count($referrals);
        
        // Get referral commissions
        $referralModel = new ReferralCommission();
        $referralCommissions = $referralModel->getUserTotalCommissions($userId);
        
        return [
            'total_mining_power' => $totalMiningPower,
            'total_rewards' => $totalRewards,
            'active_packages' => count($activePackages),
            'referral_count' => $referralCount,
            'referral_commissions' => $referralCommissions
        ];
    }
    
    /**
     * Get user activities (recent transactions, mining rewards, etc.)
     *
     * @param int $userId User ID
     * @param int $limit Number of activities to get
     * @return array User activities
     */
    public function getUserActivities($userId, $limit = 10) {
        $activityModel = new ActivityLog();
        return $activityModel->getUserActivities($userId, $limit);
    }
    
    /**
     * Check if a username or email is already taken
     *
     * @param string $username Username to check
     * @param string $email Email to check
     * @param int $excludeId User ID to exclude from the check (for updates)
     * @return array Associative array with 'username' and 'email' keys, each being true if taken
     */
    public function checkCredentialsTaken($username, $email, $excludeId = null) {
        $result = [
            'username' => false,
            'email' => false
        ];
        
        // Check username
        $sql = "SELECT id FROM {$this->table} WHERE username = :username";
        $params = ['username' => $username];
        
        if ($excludeId !== null) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }
        
        $usernameCheck = $this->raw($sql, $params, false);
        if ($usernameCheck) {
            $result['username'] = true;
        }
        
        // Check email
        $sql = "SELECT id FROM {$this->table} WHERE email = :email";
        $params = ['email' => $email];
        
        if ($excludeId !== null) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }
        
        $emailCheck = $this->raw($sql, $params, false);
        if ($emailCheck) {
            $result['email'] = true;
        }
        
        return $result;
    }
    
    /**
     * Get dashboard statistics for admin
     *
     * @return array Dashboard statistics
     */
    public function getDashboardStats() {
        // Total users
        $totalUsers = $this->count();
        
        // Active users (logged in within last 30 days)
        $thirtyDaysAgo = date('Y-m-d H:i:s', strtotime('-30 days'));
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE last_login >= :date";
        $activeUsers = $this->raw($sql, ['date' => $thirtyDaysAgo], false)->count;
        
        // New users (registered within last 30 days)
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE created_at >= :date";
        $newUsers = $this->raw($sql, ['date' => $thirtyDaysAgo], false)->count;
        
        // KYC verified users
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE kyc_verified = 1";
        $kycVerifiedUsers = $this->raw($sql, [], false)->count;
        
        // Users by role
        $sql = "SELECT role, COUNT(*) as count FROM {$this->table} GROUP BY role";
        $usersByRole = $this->raw($sql);
        
        // Users by country
        $sql = "SELECT country, COUNT(*) as count FROM {$this->table} WHERE country IS NOT NULL GROUP BY country ORDER BY count DESC LIMIT 10";
        $usersByCountry = $this->raw($sql);
        
        return [
            'total_users' => $totalUsers,
            'active_users' => $activeUsers,
            'new_users' => $newUsers,
            'kyc_verified_users' => $kycVerifiedUsers,
            'users_by_role' => $usersByRole,
            'users_by_country' => $usersByCountry
        ];
    }
    
    /**
     * Change user password
     *
     * @param int $userId User ID
     * @param string $currentPassword Current password
     * @param string $newPassword New password
     * @return bool Success or failure
     */
    public function changePassword($userId, $currentPassword, $newPassword) {
        $user = $this->find($userId);
        
        if (!$user) {
            return false;
        }
        
        // Verify current password
        if (!password_verify($currentPassword, $user->password)) {
            return false;
        }
        
        // Update with new password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        return $this->update($userId, ['password' => $hashedPassword]);
    }
    
    /**
     * Get a readable label for user roles
     *
     * @param string $role Role
     * @return string Role label
     */
    public static function getRoleLabel($role) {
        $labels = [
            self::ROLE_ADMIN => 'Administrator',
            self::ROLE_USER => 'User',
            self::ROLE_MANAGER => 'Manager'
        ];
        
        return $labels[$role] ?? 'Unknown';
    }
    
    /**
     * Get a readable label for user statuses
     *
     * @param string $status Status
     * @return string Status label
     */
    public static function getStatusLabel($status) {
        $labels = [
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_INACTIVE => 'Inactive',
            self::STATUS_SUSPENDED => 'Suspended',
            self::STATUS_BANNED => 'Banned'
        ];
        
        return $labels[$status] ?? 'Unknown';
    }
} 