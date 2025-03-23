<?php
namespace App\Models;

use App\Core\Model;

/**
 * Setting Model
 * 
 * Handles system settings management
 */
class Setting extends Model {
    // Table name
    protected $table = 'settings';
    
    // Fields that can be mass assigned
    protected $fillable = [
        'key', 'value', 'type', 'group', 'description', 'is_public'
    ];
    
    // Setting types
    const TYPE_STRING = 'string';
    const TYPE_INTEGER = 'integer';
    const TYPE_FLOAT = 'float';
    const TYPE_BOOLEAN = 'boolean';
    const TYPE_JSON = 'json';
    const TYPE_ARRAY = 'array';
    
    // Setting groups
    const GROUP_GENERAL = 'general';
    const GROUP_EMAIL = 'email';
    const GROUP_MINING = 'mining';
    const GROUP_REFERRAL = 'referral';
    const GROUP_SECURITY = 'security';
    const GROUP_PAYMENT = 'payment';
    const GROUP_NOTIFICATION = 'notification';
    const GROUP_SOCIAL = 'social';
    
    // Cache for settings
    protected static $cache = [];
    
    /**
     * Get a setting
     *
     * @param string $key Setting key
     * @param mixed $default Default value if setting doesn't exist
     * @return mixed Setting value
     */
    public function get($key, $default = null) {
        // Check if setting is in cache
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }
        
        // Get setting from database
        $query = "SELECT * FROM {$this->table} WHERE `key` = :key LIMIT 1";
        $setting = $this->raw($query, ['key' => $key], false);
        
        if (!$setting) {
            return $default;
        }
        
        // Cast the value based on type
        $value = $this->castValue($setting->value, $setting->type);
        
        // Cache the result for future use
        self::$cache[$key] = $value;
        
        return $value;
    }
    
    /**
     * Set a setting
     *
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @param string $type Setting type (auto-detected if null)
     * @param string $group Setting group
     * @param string $description Setting description
     * @param bool $isPublic Whether setting is public
     * @return bool Success or failure
     */
    public function set($key, $value, $type = null, $group = null, $description = null, $isPublic = false) {
        // Determine type if not specified
        if ($type === null) {
            $type = $this->detectType($value);
        }
        
        // Convert value to string based on type
        $stringValue = $this->stringifyValue($value, $type);
        
        // Check if setting exists
        $query = "SELECT id FROM {$this->table} WHERE `key` = :key LIMIT 1";
        $existing = $this->raw($query, ['key' => $key], false);
        
        if ($existing) {
            // Update existing setting
            $data = ['value' => $stringValue];
            
            if ($type !== null) {
                $data['type'] = $type;
            }
            
            if ($group !== null) {
                $data['group'] = $group;
            }
            
            if ($description !== null) {
                $data['description'] = $description;
            }
            
            if ($isPublic !== null) {
                $data['is_public'] = $isPublic ? 1 : 0;
            }
            
            $result = $this->update($existing->id, $data);
        } else {
            // Create new setting
            $data = [
                'key' => $key,
                'value' => $stringValue,
                'type' => $type,
                'group' => $group ?: self::GROUP_GENERAL,
                'description' => $description ?: '',
                'is_public' => $isPublic ? 1 : 0,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $result = $this->create($data) ? true : false;
        }
        
        // Update cache
        if ($result) {
            self::$cache[$key] = $value;
        }
        
        return $result;
    }
    
    /**
     * Delete a setting
     *
     * @param string $key Setting key
     * @return bool Success or failure
     */
    public function delete($key) {
        $query = "DELETE FROM {$this->table} WHERE `key` = :key";
        $result = $this->execute($query, ['key' => $key]);
        
        // Remove from cache
        if ($result && isset(self::$cache[$key])) {
            unset(self::$cache[$key]);
        }
        
        return $result;
    }
    
    /**
     * Get all settings
     *
     * @param string $group Optional group filter
     * @param bool $publicOnly Whether to return only public settings
     * @return array All settings
     */
    public function getAll($group = null, $publicOnly = false) {
        $query = "SELECT * FROM {$this->table}";
        $params = [];
        
        if ($group !== null) {
            $query .= " WHERE `group` = :group";
            $params['group'] = $group;
            
            if ($publicOnly) {
                $query .= " AND is_public = 1";
            }
        } elseif ($publicOnly) {
            $query .= " WHERE is_public = 1";
        }
        
        $query .= " ORDER BY `group` ASC, `key` ASC";
        
        $settings = $this->raw($query, $params);
        $result = [];
        
        foreach ($settings as $setting) {
            $result[$setting->key] = $this->castValue($setting->value, $setting->type);
        }
        
        return $result;
    }
    
    /**
     * Get all settings in a group
     *
     * @param string $group Group name
     * @return array Settings in the group
     */
    public function getGroup($group) {
        return $this->getAll($group);
    }
    
    /**
     * Get all public settings
     *
     * @return array Public settings
     */
    public function getPublic() {
        return $this->getAll(null, true);
    }
    
    /**
     * Initialize default settings
     *
     * @return bool Success or failure
     */
    public function initializeDefaults() {
        $defaults = [
            // General settings
            'site_name' => ['Tron Mining', self::TYPE_STRING, self::GROUP_GENERAL, 'Site name', true],
            'site_description' => ['Cloud Mining Platform for TRON', self::TYPE_STRING, self::GROUP_GENERAL, 'Site description', true],
            'admin_email' => ['admin@example.com', self::TYPE_STRING, self::GROUP_GENERAL, 'Admin email address', false],
            'timezone' => ['UTC', self::TYPE_STRING, self::GROUP_GENERAL, 'Default timezone', true],
            'maintenance_mode' => [false, self::TYPE_BOOLEAN, self::GROUP_GENERAL, 'Whether site is in maintenance mode', true],
            'maintenance_message' => ['Site is under maintenance. Please check back later.', self::TYPE_STRING, self::GROUP_GENERAL, 'Maintenance mode message', true],
            
            // Security settings
            'require_email_verification' => [true, self::TYPE_BOOLEAN, self::GROUP_SECURITY, 'Require email verification for new accounts', false],
            'require_kyc_verification' => [true, self::TYPE_BOOLEAN, self::GROUP_SECURITY, 'Require KYC verification for withdrawals', false],
            'google_recaptcha_site_key' => ['', self::TYPE_STRING, self::GROUP_SECURITY, 'Google reCAPTCHA site key', false],
            'google_recaptcha_secret_key' => ['', self::TYPE_STRING, self::GROUP_SECURITY, 'Google reCAPTCHA secret key', false],
            'use_recaptcha' => [false, self::TYPE_BOOLEAN, self::GROUP_SECURITY, 'Use Google reCAPTCHA', false],
            'min_password_length' => [8, self::TYPE_INTEGER, self::GROUP_SECURITY, 'Minimum password length', false],
            
            // Mining settings
            'mining_reward_rate' => [0.001, self::TYPE_FLOAT, self::GROUP_MINING, 'Mining reward rate per mining power', true],
            'min_withdrawal_amount' => [100, self::TYPE_FLOAT, self::GROUP_MINING, 'Minimum withdrawal amount', true],
            'withdrawal_fee' => [10, self::TYPE_FLOAT, self::GROUP_MINING, 'Withdrawal fee', true],
            'min_deposit_amount' => [50, self::TYPE_FLOAT, self::GROUP_MINING, 'Minimum deposit amount', true],
            'deposit_confirmations' => [10, self::TYPE_INTEGER, self::GROUP_MINING, 'Required confirmations for deposits', true],
            
            // Referral settings
            'referral_enabled' => [true, self::TYPE_BOOLEAN, self::GROUP_REFERRAL, 'Enable referral system', true],
            'referral_levels' => [3, self::TYPE_INTEGER, self::GROUP_REFERRAL, 'Number of referral levels', true],
            'referral_level1_rate' => [5, self::TYPE_FLOAT, self::GROUP_REFERRAL, 'Level 1 referral commission (%)', true],
            'referral_level2_rate' => [2, self::TYPE_FLOAT, self::GROUP_REFERRAL, 'Level 2 referral commission (%)', true],
            'referral_level3_rate' => [1, self::TYPE_FLOAT, self::GROUP_REFERRAL, 'Level 3 referral commission (%)', true],
            'referral_deposit_commission' => [5, self::TYPE_FLOAT, self::GROUP_REFERRAL, 'Commission for referral deposits (%)', true],
            'referral_mining_commission' => [3, self::TYPE_FLOAT, self::GROUP_REFERRAL, 'Commission for referral mining rewards (%)', true],
            'referral_purchase_commission' => [10, self::TYPE_FLOAT, self::GROUP_REFERRAL, 'Commission for referral purchases (%)', true],
            
            // Email settings
            'mail_driver' => ['smtp', self::TYPE_STRING, self::GROUP_EMAIL, 'Mail driver (smtp, sendmail, etc.)', false],
            'mail_host' => ['smtp.example.com', self::TYPE_STRING, self::GROUP_EMAIL, 'SMTP host', false],
            'mail_port' => [587, self::TYPE_INTEGER, self::GROUP_EMAIL, 'SMTP port', false],
            'mail_username' => ['', self::TYPE_STRING, self::GROUP_EMAIL, 'SMTP username', false],
            'mail_password' => ['', self::TYPE_STRING, self::GROUP_EMAIL, 'SMTP password', false],
            'mail_encryption' => ['tls', self::TYPE_STRING, self::GROUP_EMAIL, 'SMTP encryption (tls, ssl)', false],
            'mail_from_address' => ['noreply@example.com', self::TYPE_STRING, self::GROUP_EMAIL, 'From email address', false],
            'mail_from_name' => ['Tron Mining', self::TYPE_STRING, self::GROUP_EMAIL, 'From name', false],
            
            // Social links
            'social_facebook' => ['', self::TYPE_STRING, self::GROUP_SOCIAL, 'Facebook URL', true],
            'social_twitter' => ['', self::TYPE_STRING, self::GROUP_SOCIAL, 'Twitter URL', true],
            'social_instagram' => ['', self::TYPE_STRING, self::GROUP_SOCIAL, 'Instagram URL', true],
            'social_telegram' => ['', self::TYPE_STRING, self::GROUP_SOCIAL, 'Telegram URL', true],
            'social_discord' => ['', self::TYPE_STRING, self::GROUP_SOCIAL, 'Discord URL', true],
            
            // Payment settings
            'currency' => ['TRX', self::TYPE_STRING, self::GROUP_PAYMENT, 'Default currency', true],
            'tron_network' => ['mainnet', self::TYPE_STRING, self::GROUP_PAYMENT, 'TRON network (mainnet or testnet)', false],
            'tron_api_key' => ['', self::TYPE_STRING, self::GROUP_PAYMENT, 'TRON API key', false],
            'tron_api_url' => ['https://api.trongrid.io', self::TYPE_STRING, self::GROUP_PAYMENT, 'TRON API URL', false],
            'tron_wallet_address' => ['', self::TYPE_STRING, self::GROUP_PAYMENT, 'TRON wallet address', false],
            'tron_private_key' => ['', self::TYPE_STRING, self::GROUP_PAYMENT, 'TRON private key', false],
            
            // Notification settings
            'notifications_email' => [true, self::TYPE_BOOLEAN, self::GROUP_NOTIFICATION, 'Enable email notifications', false],
            'notifications_browser' => [true, self::TYPE_BOOLEAN, self::GROUP_NOTIFICATION, 'Enable browser notifications', true],
            'admin_email_notifications' => [true, self::TYPE_BOOLEAN, self::GROUP_NOTIFICATION, 'Send email notifications to admin', false]
        ];
        
        foreach ($defaults as $key => $setting) {
            $this->set(
                $key,
                $setting[0],
                $setting[1],
                $setting[2],
                $setting[3],
                $setting[4]
            );
        }
        
        return true;
    }
    
    /**
     * Clear settings cache
     *
     * @return void
     */
    public function clearCache() {
        self::$cache = [];
    }
    
    /**
     * Cast a value from string to its proper type
     *
     * @param string $value Value as string
     * @param string $type Type to cast to
     * @return mixed Casted value
     */
    protected function castValue($value, $type) {
        switch ($type) {
            case self::TYPE_INTEGER:
                return (int) $value;
            
            case self::TYPE_FLOAT:
                return (float) $value;
            
            case self::TYPE_BOOLEAN:
                return ($value === '1' || $value === 'true' || $value === true);
            
            case self::TYPE_JSON:
            case self::TYPE_ARRAY:
                return json_decode($value, true);
            
            case self::TYPE_STRING:
            default:
                return $value;
        }
    }
    
    /**
     * Convert a value to string for storage
     *
     * @param mixed $value Value to stringify
     * @param string $type Value type
     * @return string String representation
     */
    protected function stringifyValue($value, $type) {
        switch ($type) {
            case self::TYPE_BOOLEAN:
                return $value ? '1' : '0';
            
            case self::TYPE_JSON:
            case self::TYPE_ARRAY:
                return json_encode($value);
            
            case self::TYPE_INTEGER:
            case self::TYPE_FLOAT:
            case self::TYPE_STRING:
            default:
                return (string) $value;
        }
    }
    
    /**
     * Detect the type of a value
     *
     * @param mixed $value Value to detect type of
     * @return string Detected type
     */
    protected function detectType($value) {
        if (is_bool($value)) {
            return self::TYPE_BOOLEAN;
        } elseif (is_int($value)) {
            return self::TYPE_INTEGER;
        } elseif (is_float($value)) {
            return self::TYPE_FLOAT;
        } elseif (is_array($value)) {
            return self::TYPE_ARRAY;
        } elseif (is_object($value)) {
            return self::TYPE_JSON;
        } else {
            return self::TYPE_STRING;
        }
    }
    
    /**
     * Get all available setting groups
     *
     * @return array Setting groups
     */
    public function getGroups() {
        $query = "SELECT DISTINCT `group` FROM {$this->table} ORDER BY `group`";
        $results = $this->raw($query);
        
        $groups = [];
        foreach ($results as $result) {
            $groups[] = $result->group;
        }
        
        return $groups;
    }
    
    /**
     * Import settings from array
     *
     * @param array $settings Settings array
     * @return int Number of imported settings
     */
    public function importSettings($settings) {
        $count = 0;
        
        foreach ($settings as $key => $data) {
            if (!is_array($data) || !isset($data['value'])) {
                continue;
            }
            
            $value = $data['value'];
            $type = isset($data['type']) ? $data['type'] : null;
            $group = isset($data['group']) ? $data['group'] : null;
            $description = isset($data['description']) ? $data['description'] : null;
            $isPublic = isset($data['is_public']) ? (bool) $data['is_public'] : false;
            
            if ($this->set($key, $value, $type, $group, $description, $isPublic)) {
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Export settings to array
     *
     * @param string $group Optional group filter
     * @param bool $includePrivate Whether to include private settings
     * @return array Settings array
     */
    public function exportSettings($group = null, $includePrivate = false) {
        $query = "SELECT * FROM {$this->table}";
        $params = [];
        
        if ($group !== null) {
            $query .= " WHERE `group` = :group";
            $params['group'] = $group;
        }
        
        if (!$includePrivate) {
            if ($group !== null) {
                $query .= " AND is_public = 1";
            } else {
                $query .= " WHERE is_public = 1";
            }
        }
        
        $settings = $this->raw($query, $params);
        $result = [];
        
        foreach ($settings as $setting) {
            $result[$setting->key] = [
                'value' => $this->castValue($setting->value, $setting->type),
                'type' => $setting->type,
                'group' => $setting->group,
                'description' => $setting->description,
                'is_public' => (bool) $setting->is_public
            ];
        }
        
        return $result;
    }
} 