<?php
/**
 * Main Configuration File
 * 
 * This file loads environment variables and sets up application configuration
 */

// Define the application version if not defined
if (!defined('APP_VERSION')) {
    define('APP_VERSION', '1.0.0');
}

// Define the root path for the application if not defined
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', realpath(dirname(__FILE__) . '/../../'));
}

if (!defined('APP_PATH')) {
    define('APP_PATH', ROOT_PATH . '/app');
}

if (!defined('PUBLIC_PATH')) {
    define('PUBLIC_PATH', ROOT_PATH . '/public');
}

if (!defined('STORAGE_PATH')) {
    define('STORAGE_PATH', ROOT_PATH . '/storage');
}

if (!defined('VENDOR_PATH')) {
    define('VENDOR_PATH', ROOT_PATH . '/vendor');
}

// Load environment variables
$env_file = ROOT_PATH . '/.env';
if (file_exists($env_file)) {
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        
        // Remove quotes if present
        if (strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) {
            $value = substr($value, 1, -1);
        } elseif (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1) {
            $value = substr($value, 1, -1);
        }
        
        // Parse variables in values ${VAR_NAME}
        if (preg_match_all('/\${([^}]+)}/', $value, $matches)) {
            foreach ($matches[0] as $i => $placeholder) {
                $envVar = $matches[1][$i];
                $envValue = getenv($envVar) ?: $_ENV[$envVar] ?? '';
                $value = str_replace($placeholder, $envValue, $value);
            }
        }
        
        putenv("$name=$value");
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

// Database configuration
$config['db'] = [
    'connection' => getenv('DB_CONNECTION') ?: 'mysql',
    'host'       => getenv('DB_HOST') ?: 'localhost',
    'port'       => getenv('DB_PORT') ?: '3306',
    'database'   => getenv('DB_DATABASE') ?: 'tronmining',
    'username'   => getenv('DB_USERNAME') ?: 'root',
    'password'   => getenv('DB_PASSWORD') ?: '',
    'charset'    => 'utf8mb4',
    'collation'  => 'utf8mb4_unicode_ci',
    'prefix'     => '',
];

// Application configuration
$config['app'] = [
    'name'      => getenv('APP_NAME') ?: 'Tron Mining',
    'env'       => getenv('APP_ENV') ?: 'development',
    'debug'     => getenv('APP_DEBUG') === 'true',
    'url'       => getenv('APP_URL') ?: 'http://localhost',
    'timezone'  => getenv('APP_TIMEZONE') ?: 'UTC',
    'locale'    => 'en',
    'key'       => getenv('ENCRYPTION_KEY') ?: 'base64:'.base64_encode(random_bytes(32)),
    'version'   => '1.0.0',
    'base_path' => '',
    'secret_key' => 'your-secret-key-here'
];

// Mail configuration
$config['mail'] = [
    'driver'       => getenv('MAIL_DRIVER') ?: 'smtp',
    'host'         => getenv('MAIL_HOST') ?: 'smtp.gmail.com',
    'port'         => getenv('MAIL_PORT') ?: '587',
    'username'     => getenv('MAIL_USERNAME') ?: '',
    'password'     => getenv('MAIL_PASSWORD') ?: '',
    'encryption'   => getenv('MAIL_ENCRYPTION') ?: 'tls',
    'from_address' => getenv('MAIL_FROM_ADDRESS') ?: 'hello@example.com',
    'from_name'    => getenv('MAIL_FROM_NAME') ?: 'Tron Mining',
];

// PayKassa configuration
$config['paykassa'] = [
    'merchant_id'  => getenv('PAYKASSA_MERCHANT_ID') ?: '',
    'api_key'      => getenv('PAYKASSA_API_KEY') ?: '',
    'secret_key'   => getenv('PAYKASSA_SECRET_KEY') ?: '',
    'test_mode'    => getenv('PAYKASSA_TEST_MODE') === 'true',
];

// Mining configuration
$config['mining'] = [
    'reward_rate' => 0.001, // per mining power
    'min_withdrawal' => 100,
    'withdrawal_fee' => 10,
    'min_deposit' => 50,
    'deposit_confirmations' => 10,
    'default_rate'        => (float) getenv('DEFAULT_MINING_RATE') ?: 0.01,
    'referral_commission' => (float) getenv('REFERRAL_COMMISSION') ?: 0.05,
];

// Storage configuration
$config['storage'] = [
    'driver' => getenv('STORAGE_DRIVER') ?: 'local',
];

// Supported currencies
$config['currencies'] = [
    'BTC'  => ['name' => 'Bitcoin', 'symbol' => '₿', 'decimals' => 8],
    'ETH'  => ['name' => 'Ethereum', 'symbol' => 'Ξ', 'decimals' => 8],
    'LTC'  => ['name' => 'Litecoin', 'symbol' => 'Ł', 'decimals' => 8],
    'DOGE' => ['name' => 'Dogecoin', 'symbol' => 'Ð', 'decimals' => 8],
    'USDT' => ['name' => 'Tether USD', 'symbol' => '₮', 'decimals' => 6],
    'TRX'  => ['name' => 'TRON', 'symbol' => 'TRX', 'decimals' => 6],
];

// Session configuration
$config['session'] = [
    'lifetime' => 120, // minutes
    'secure' => false, // only cookies over https
    'httponly' => true, // cookies only accessible through HTTP
    'path' => '/',
    'domain' => null,
    'same_site' => 'lax' // none, lax, strict
];

// Security configuration
$config['security'] = [
    'password_hash_algo' => PASSWORD_BCRYPT,
    'password_hash_options' => [
        'cost' => 12
    ],
    'token_lifetime' => 60, // minutes
    'csrf_protection' => true,
    'max_login_attempts' => 5,
    'lockout_time' => 15, // minutes
    'require_email_verification' => true
];

// Referral configuration
$config['referral'] = [
    'enabled' => true,
    'levels' => 3,
    'commissions' => [
        1 => 5, // 5% for level 1
        2 => 2, // 2% for level 2
        3 => 1  // 1% for level 3
    ]
];

// Pagination configuration
$config['pagination'] = [
    'per_page' => 15,
    'max_per_page' => 100
];

// Uploads configuration
$config['uploads'] = [
    'max_size' => 5 * 1024 * 1024, // 5MB
    'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'],
    'storage_path' => ROOT_PATH . '/public/uploads',
    'storage_url' => '/uploads'
];

// Tron configuration
$config['tron'] = [
    'network' => getenv('TRON_NETWORK') ?: 'mainnet', // mainnet, shasta
    'api_key' => getenv('TRON_API_KEY') ?: '',
    'api_url' => getenv('TRON_API_URL') ?: 'https://api.trongrid.io',
    'explorer_url' => 'https://tronscan.org'
];

// Cache configuration
$config['cache'] = [
    'driver' => 'file', // file, redis, memcached
    'path' => ROOT_PATH . '/storage/cache',
    'lifetime' => 60 // minutes
];

// Logging configuration
$config['logging'] = [
    'path' => ROOT_PATH . '/storage/logs',
    'max_files' => 30,
    'level' => 'debug' // debug, info, notice, warning, error, critical, alert, emergency
];

// Return the configuration array
return $config; 