<?php
namespace App\Models;

use App\Core\Model;

/**
 * MiningPackage Model
 * 
 * Handles mining package-related database operations
 */
class MiningPackage extends Model {
    // Table name
    protected $table = 'mining_packages';
    
    // Fields that can be mass assigned
    protected $fillable = [
        'name', 'description', 'price', 'mining_power', 'duration', 
        'daily_reward_rate', 'currency', 'image', 'status'
    ];
    
    /**
     * Get active mining packages
     *
     * @return array Active mining packages
     */
    public function getActivePackages() {
        return $this->where('status', 'active');
    }
    
    /**
     * Get mining package by ID
     *
     * @param int $id Package ID
     * @return object|null Package data or null if not found
     */
    public function getPackage($id) {
        return $this->find($id);
    }
    
    /**
     * Get mining packages by currency
     *
     * @param string $currency Currency code
     * @param bool $activeOnly Whether to return active packages only
     * @return array Mining packages for the specified currency
     */
    public function getPackagesByCurrency($currency, $activeOnly = true) {
        $sql = "SELECT * FROM {$this->table} WHERE currency = :currency";
        
        if ($activeOnly) {
            $sql .= " AND status = 'active'";
        }
        
        $sql .= " ORDER BY price ASC";
        
        return $this->raw($sql, ['currency' => $currency]);
    }
    
    /**
     * Calculate daily reward for a package
     *
     * @param int $packageId Package ID
     * @param float $miningPower Mining power (optional, uses package's mining power if not provided)
     * @return float Daily reward
     */
    public function calculateDailyReward($packageId, $miningPower = null) {
        $package = $this->find($packageId);
        
        if (!$package) {
            return 0;
        }
        
        if ($miningPower === null) {
            $miningPower = $package->mining_power;
        }
        
        return $miningPower * $package->daily_reward_rate;
    }
    
    /**
     * Calculate total reward for a package over its duration
     *
     * @param int $packageId Package ID
     * @return float Total reward
     */
    public function calculateTotalReward($packageId) {
        $package = $this->find($packageId);
        
        if (!$package) {
            return 0;
        }
        
        $dailyReward = $this->calculateDailyReward($packageId);
        
        return $dailyReward * $package->duration;
    }
    
    /**
     * Calculate ROI (Return on Investment) for a package
     *
     * @param int $packageId Package ID
     * @return float ROI percentage
     */
    public function calculateROI($packageId) {
        $package = $this->find($packageId);
        
        if (!$package || $package->price == 0) {
            return 0;
        }
        
        $totalReward = $this->calculateTotalReward($packageId);
        
        return ($totalReward / $package->price) * 100;
    }
    
    /**
     * Initialize default mining packages
     * 
     * This method creates default mining packages if none exist
     * 
     * @return array Created package IDs
     */
    public function initializeDefaultPackages() {
        // Check if there are any packages
        $sql = "SELECT COUNT(*) as count FROM {$this->table}";
        $result = $this->raw($sql, [], false);
        
        if ($result && $result->count > 0) {
            return []; // Packages already exist
        }
        
        // Define default packages
        $packages = [
            [
                'name' => 'Starter Package',
                'description' => 'Perfect for beginners. Start mining Bitcoin with low investment.',
                'price' => 100,
                'mining_power' => 10,
                'duration' => 30,
                'daily_reward_rate' => 0.005,
                'currency' => 'BTC',
                'image' => 'package-starter.png',
                'status' => 'active'
            ],
            [
                'name' => 'Advanced Package',
                'description' => 'Increased mining power for serious miners.',
                'price' => 500,
                'mining_power' => 60,
                'duration' => 60,
                'daily_reward_rate' => 0.006,
                'currency' => 'BTC',
                'image' => 'package-advanced.png',
                'status' => 'active'
            ],
            [
                'name' => 'Professional Package',
                'description' => 'High mining power with extended duration.',
                'price' => 1000,
                'mining_power' => 150,
                'duration' => 90,
                'daily_reward_rate' => 0.007,
                'currency' => 'BTC',
                'image' => 'package-professional.png',
                'status' => 'active'
            ],
            [
                'name' => 'Enterprise Package',
                'description' => 'Maximum mining power for maximum rewards.',
                'price' => 5000,
                'mining_power' => 1000,
                'duration' => 180,
                'daily_reward_rate' => 0.008,
                'currency' => 'BTC',
                'image' => 'package-enterprise.png',
                'status' => 'active'
            ],
            [
                'name' => 'Ethereum Starter',
                'description' => 'Start mining Ethereum with low investment.',
                'price' => 100,
                'mining_power' => 12,
                'duration' => 30,
                'daily_reward_rate' => 0.006,
                'currency' => 'ETH',
                'image' => 'package-eth-starter.png',
                'status' => 'active'
            ],
            [
                'name' => 'Ethereum Professional',
                'description' => 'Professional Ethereum mining package.',
                'price' => 1000,
                'mining_power' => 180,
                'duration' => 90,
                'daily_reward_rate' => 0.008,
                'currency' => 'ETH',
                'image' => 'package-eth-professional.png',
                'status' => 'active'
            ],
            [
                'name' => 'TRON Starter',
                'description' => 'Start mining TRON with low investment.',
                'price' => 100,
                'mining_power' => 15000,
                'duration' => 30,
                'daily_reward_rate' => 0.007,
                'currency' => 'TRX',
                'image' => 'package-trx-starter.png',
                'status' => 'active'
            ],
            [
                'name' => 'TRON Professional',
                'description' => 'Professional TRON mining package.',
                'price' => 1000,
                'mining_power' => 200000,
                'duration' => 90,
                'daily_reward_rate' => 0.009,
                'currency' => 'TRX',
                'image' => 'package-trx-professional.png',
                'status' => 'active'
            ]
        ];
        
        $createdIds = [];
        
        foreach ($packages as $package) {
            $id = $this->create($package);
            if ($id) {
                $createdIds[] = $id;
            }
        }
        
        return $createdIds;
    }
    
    /**
     * Get popular mining packages
     *
     * @param int $limit Limit (default: 4)
     * @return array Popular mining packages
     */
    public function getPopularPackages($limit = 4) {
        // In a real system, you would determine popularity based on sales
        // For simplicity, we'll use a predefined list or just return the active packages
        
        $sql = "SELECT mp.*, 
                       (SELECT COUNT(*) FROM user_mining_power ump WHERE ump.package_id = mp.id) as purchase_count
                FROM {$this->table} mp
                WHERE mp.status = 'active'
                ORDER BY purchase_count DESC, mp.price ASC
                LIMIT :limit";
        
        return $this->raw($sql, ['limit' => $limit]);
    }
    
    /**
     * Calculate profitability metrics for a package
     *
     * @param int $packageId Package ID
     * @return array Profitability metrics
     */
    public function calculateProfitabilityMetrics($packageId) {
        $package = $this->find($packageId);
        
        if (!$package) {
            return [
                'daily_reward' => 0,
                'monthly_reward' => 0,
                'total_reward' => 0,
                'roi' => 0,
                'roi_percentage' => 0,
                'break_even_days' => 0
            ];
        }
        
        $dailyReward = $this->calculateDailyReward($packageId);
        $totalReward = $this->calculateTotalReward($packageId);
        $roi = $this->calculateROI($packageId);
        
        // Calculate break-even point in days
        $breakEvenDays = 0;
        if ($dailyReward > 0) {
            $breakEvenDays = $package->price / $dailyReward;
        }
        
        return [
            'daily_reward' => $dailyReward,
            'monthly_reward' => $dailyReward * 30,
            'total_reward' => $totalReward,
            'roi' => $totalReward - $package->price,
            'roi_percentage' => $roi,
            'break_even_days' => $breakEvenDays
        ];
    }
} 