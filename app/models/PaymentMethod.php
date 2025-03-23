<?php
namespace App\Models;

use App\Core\Model;

/**
 * PaymentMethod Model
 * 
 * Handles payment methods available in the system
 */
class PaymentMethod extends Model {
    // Table name
    protected $table = 'payment_methods';
    
    // Fields that can be mass assigned
    protected $fillable = [
        'name', 'code', 'description', 'logo', 
        'min_amount', 'max_amount', 'fee_percentage', 
        'fee_fixed', 'processing_time', 'instructions',
        'is_crypto', 'wallet_address', 'qr_code',
        'status', 'sort_order'
    ];
    
    // Status constants
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_MAINTENANCE = 'maintenance';
    
    // Type constants
    const TYPE_DEPOSIT = 'deposit';
    const TYPE_WITHDRAWAL = 'withdrawal';
    const TYPE_BOTH = 'both';
    
    /**
     * Get all active payment methods
     *
     * @param string $type Type of payment methods (deposit, withdrawal, both)
     * @return array Active payment methods
     */
    public function getActiveMethods($type = null) {
        $query = "SELECT * FROM {$this->table} WHERE status = :status";
        $params = ['status' => self::STATUS_ACTIVE];
        
        if ($type !== null) {
            $query .= " AND (type = :type OR type = :both)";
            $params['type'] = $type;
            $params['both'] = self::TYPE_BOTH;
        }
        
        $query .= " ORDER BY sort_order ASC, name ASC";
        
        return $this->raw($query, $params);
    }
    
    /**
     * Get payment method by ID
     *
     * @param int $id Payment method ID
     * @return object|null Payment method or null if not found
     */
    public function getById($id) {
        return $this->find($id);
    }
    
    /**
     * Get payment method by code
     *
     * @param string $code Payment method code
     * @return object|null Payment method or null if not found
     */
    public function getByCode($code) {
        $query = "SELECT * FROM {$this->table} WHERE code = :code LIMIT 1";
        return $this->raw($query, ['code' => $code], false);
    }
    
    /**
     * Check if payment method is active
     *
     * @param int $id Payment method ID
     * @return bool Whether payment method is active
     */
    public function isActive($id) {
        $method = $this->find($id);
        return $method && $method->status === self::STATUS_ACTIVE;
    }
    
    /**
     * Get crypto payment methods
     *
     * @param bool $activeOnly Whether to return only active methods
     * @return array Crypto payment methods
     */
    public function getCryptoMethods($activeOnly = true) {
        $query = "SELECT * FROM {$this->table} WHERE is_crypto = 1";
        
        if ($activeOnly) {
            $query .= " AND status = :status";
        }
        
        $query .= " ORDER BY sort_order ASC, name ASC";
        
        $params = $activeOnly ? ['status' => self::STATUS_ACTIVE] : [];
        
        return $this->raw($query, $params);
    }
    
    /**
     * Get non-crypto payment methods
     *
     * @param bool $activeOnly Whether to return only active methods
     * @return array Non-crypto payment methods
     */
    public function getNonCryptoMethods($activeOnly = true) {
        $query = "SELECT * FROM {$this->table} WHERE is_crypto = 0";
        
        if ($activeOnly) {
            $query .= " AND status = :status";
        }
        
        $query .= " ORDER BY sort_order ASC, name ASC";
        
        $params = $activeOnly ? ['status' => self::STATUS_ACTIVE] : [];
        
        return $this->raw($query, $params);
    }
    
    /**
     * Create a new payment method
     *
     * @param array $data Payment method data
     * @return int|bool ID of created payment method or false on failure
     */
    public function createMethod($data) {
        // Set default values if not provided
        if (!isset($data['status'])) {
            $data['status'] = self::STATUS_ACTIVE;
        }
        
        if (!isset($data['sort_order'])) {
            $data['sort_order'] = 0;
        }
        
        if (!isset($data['is_crypto'])) {
            $data['is_crypto'] = 0;
        }
        
        if (!isset($data['fee_percentage'])) {
            $data['fee_percentage'] = 0;
        }
        
        if (!isset($data['fee_fixed'])) {
            $data['fee_fixed'] = 0;
        }
        
        // Check for duplicate code
        $existing = $this->getByCode($data['code']);
        if ($existing) {
            return false;
        }
        
        // Create payment method
        return $this->create($data);
    }
    
    /**
     * Update payment method
     *
     * @param int $id Payment method ID
     * @param array $data Payment method data to update
     * @return bool Success or failure
     */
    public function updateMethod($id, $data) {
        // Check if payment method exists
        $method = $this->find($id);
        if (!$method) {
            return false;
        }
        
        // Check for duplicate code if code is being changed
        if (isset($data['code']) && $data['code'] !== $method->code) {
            $existing = $this->getByCode($data['code']);
            if ($existing) {
                return false;
            }
        }
        
        // Update payment method
        return $this->update($id, $data);
    }
    
    /**
     * Activate a payment method
     *
     * @param int $id Payment method ID
     * @return bool Success or failure
     */
    public function activateMethod($id) {
        return $this->update($id, [
            'status' => self::STATUS_ACTIVE,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Deactivate a payment method
     *
     * @param int $id Payment method ID
     * @return bool Success or failure
     */
    public function deactivateMethod($id) {
        return $this->update($id, [
            'status' => self::STATUS_INACTIVE,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Set payment method to maintenance mode
     *
     * @param int $id Payment method ID
     * @return bool Success or failure
     */
    public function setMaintenanceMode($id) {
        return $this->update($id, [
            'status' => self::STATUS_MAINTENANCE,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Calculate fee for a transaction
     *
     * @param int $methodId Payment method ID
     * @param float $amount Transaction amount
     * @return float Fee amount
     */
    public function calculateFee($methodId, $amount) {
        $method = $this->find($methodId);
        
        if (!$method) {
            return 0;
        }
        
        // Calculate percentage fee
        $percentageFee = $amount * ($method->fee_percentage / 100);
        
        // Add fixed fee
        $totalFee = $percentageFee + $method->fee_fixed;
        
        return round($totalFee, 2);
    }
    
    /**
     * Get final amount after fees
     *
     * @param int $methodId Payment method ID
     * @param float $amount Transaction amount
     * @param bool $isAddition Whether fee is added to amount (true) or deducted (false)
     * @return float Final amount
     */
    public function getFinalAmount($methodId, $amount, $isAddition = false) {
        $fee = $this->calculateFee($methodId, $amount);
        
        if ($isAddition) {
            return $amount + $fee;
        } else {
            return $amount - $fee;
        }
    }
    
    /**
     * Check if amount is within limits
     *
     * @param int $methodId Payment method ID
     * @param float $amount Transaction amount
     * @return bool Whether amount is within limits
     */
    public function isAmountWithinLimits($methodId, $amount) {
        $method = $this->find($methodId);
        
        if (!$method) {
            return false;
        }
        
        $minOk = $method->min_amount === null || $amount >= $method->min_amount;
        $maxOk = $method->max_amount === null || $amount <= $method->max_amount;
        
        return $minOk && $maxOk;
    }
    
    /**
     * Get payment methods for form selection
     *
     * @param bool $activeOnly Whether to include only active methods
     * @return array Payment methods for selection
     */
    public function getMethodsForSelection($activeOnly = true) {
        $query = "SELECT id, name, code FROM {$this->table}";
        
        if ($activeOnly) {
            $query .= " WHERE status = :status";
        }
        
        $query .= " ORDER BY sort_order ASC, name ASC";
        
        $params = $activeOnly ? ['status' => self::STATUS_ACTIVE] : [];
        
        $methods = $this->raw($query, $params);
        $selection = [];
        
        foreach ($methods as $method) {
            $selection[$method->id] = $method->name . ' (' . $method->code . ')';
        }
        
        return $selection;
    }
    
    /**
     * Update wallet address for crypto payment method
     *
     * @param int $id Payment method ID
     * @param string $walletAddress New wallet address
     * @param string $qrCode Optional QR code path
     * @return bool Success or failure
     */
    public function updateWalletAddress($id, $walletAddress, $qrCode = null) {
        $data = [
            'wallet_address' => $walletAddress,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        if ($qrCode !== null) {
            $data['qr_code'] = $qrCode;
        }
        
        return $this->update($id, $data);
    }
    
    /**
     * Get status label
     *
     * @param string $status Status code
     * @return string Status label
     */
    public static function getStatusLabel($status) {
        $labels = [
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_INACTIVE => 'Inactive',
            self::STATUS_MAINTENANCE => 'Maintenance'
        ];
        
        return $labels[$status] ?? 'Unknown';
    }
    
    /**
     * Get type label
     *
     * @param string $type Type code
     * @return string Type label
     */
    public static function getTypeLabel($type) {
        $labels = [
            self::TYPE_DEPOSIT => 'Deposit Only',
            self::TYPE_WITHDRAWAL => 'Withdrawal Only',
            self::TYPE_BOTH => 'Deposit & Withdrawal'
        ];
        
        return $labels[$type] ?? 'Unknown';
    }
} 