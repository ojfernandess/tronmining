<?php
/**
 * Tronmining - HYIP Script
 * Installation Script
 */

// Habilitar exibição de erros para depuração
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Definir handler de exceções para prevenir erro 500
set_exception_handler(function($exception) {
    header("HTTP/1.1 500 Internal Server Error");
    echo "<h1>Erro durante a instalação</h1>";
    echo "<p>Ocorreu um erro durante o processo de instalação:</p>";
    echo "<pre style='background: #f8f8f8; padding: 10px; border: 1px solid #ddd; border-radius: 4px;'>";
    echo $exception->getMessage() . "\n";
    echo "No arquivo: " . $exception->getFile() . " (linha " . $exception->getLine() . ")";
    echo "</pre>";
    echo "<p><a href='index.php'>Voltar para o início da instalação</a></p>";
    exit;
});

// Define paths
define('ROOT_PATH', dirname(__DIR__));
define('INSTALL_PATH', __DIR__);

// Check if already installed
if (file_exists(ROOT_PATH . '/.env') && !isset($_GET['force'])) {
    header('Location: ../index.php');
    exit;
}

// Installation steps
$steps = [
    1 => 'Welcome',
    2 => 'Requirements Check',
    3 => 'Database Configuration',
    4 => 'Administrator Setup',
    5 => 'Installation',
    6 => 'Finished'
];

// Get current step
$currentStep = isset($_GET['step']) ? (int)$_GET['step'] : 1;
if ($currentStep < 1 || $currentStep > count($steps)) {
    $currentStep = 1;
}

// Process form submissions
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($currentStep) {
        case 3:
            // Validate database connection
            $dbHost = $_POST['db_host'] ?? '';
            $dbPort = $_POST['db_port'] ?? '3306';
            $dbName = $_POST['db_name'] ?? '';
            $dbUser = $_POST['db_user'] ?? '';
            $dbPass = $_POST['db_pass'] ?? '';
            
            if (empty($dbHost) || empty($dbName) || empty($dbUser)) {
                $errors[] = 'All database fields are required except password (if not needed).';
            } else {
                try {
                    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName}";
                    $options = [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_TIMEOUT => 5
                    ];
                    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
                    
                    // Save database config for next step
                    $_SESSION['db_config'] = [
                        'host' => $dbHost,
                        'port' => $dbPort,
                        'name' => $dbName,
                        'user' => $dbUser,
                        'pass' => $dbPass
                    ];
                    
                    // Redirect to next step
                    header('Location: index.php?step=4');
                    exit;
                } catch (PDOException $e) {
                    $errors[] = 'Database connection failed: ' . $e->getMessage();
                }
            }
            break;
            
        case 4:
            // Validate admin setup
            $adminUser = $_POST['admin_user'] ?? '';
            $adminEmail = $_POST['admin_email'] ?? '';
            $adminPass = $_POST['admin_pass'] ?? '';
            $adminPassConfirm = $_POST['admin_pass_confirm'] ?? '';
            
            if (empty($adminUser) || empty($adminEmail) || empty($adminPass)) {
                $errors[] = 'All administrator fields are required.';
            } elseif (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Please enter a valid email address.';
            } elseif ($adminPass !== $adminPassConfirm) {
                $errors[] = 'Passwords do not match.';
            } elseif (strlen($adminPass) < 8) {
                $errors[] = 'Password must be at least 8 characters long.';
            } else {
                // Save admin config for next step
                $_SESSION['admin_config'] = [
                    'username' => $adminUser,
                    'email' => $adminEmail,
                    'password' => password_hash($adminPass, PASSWORD_DEFAULT)
                ];
                
                // Redirect to next step
                header('Location: index.php?step=5');
                exit;
            }
            break;
            
        case 5:
            // Perform installation
            if (!isset($_SESSION['db_config']) || !isset($_SESSION['admin_config'])) {
                header('Location: index.php?step=1');
                exit;
            }
            
            $dbConfig = $_SESSION['db_config'];
            $adminConfig = $_SESSION['admin_config'];
            $appUrl = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI'], 2);
            
            try {
                // 1. Create .env file
                $envContent = "# Application Environment\n";
                $envContent .= "APP_ENV=production\n";
                $envContent .= "APP_DEBUG=false\n";
                $envContent .= "APP_URL={$appUrl}\n";
                $envContent .= "APP_NAME=\"Tronmining - HYIP System\"\n";
                $envContent .= "APP_TIMEZONE=UTC\n\n";
                
                $envContent .= "# Database Configuration\n";
                $envContent .= "DB_CONNECTION=mysql\n";
                $envContent .= "DB_HOST={$dbConfig['host']}\n";
                $envContent .= "DB_PORT={$dbConfig['port']}\n";
                $envContent .= "DB_DATABASE={$dbConfig['name']}\n";
                $envContent .= "DB_USERNAME={$dbConfig['user']}\n";
                $envContent .= "DB_PASSWORD={$dbConfig['pass']}\n\n";
                
                $envContent .= "# Mail Configuration\n";
                $envContent .= "MAIL_DRIVER=smtp\n";
                $envContent .= "MAIL_HOST=smtp.mailtrap.io\n";
                $envContent .= "MAIL_PORT=2525\n";
                $envContent .= "MAIL_USERNAME=null\n";
                $envContent .= "MAIL_PASSWORD=null\n";
                $envContent .= "MAIL_ENCRYPTION=tls\n";
                $envContent .= "MAIL_FROM_ADDRESS=noreply@tronmining.com\n";
                $envContent .= "MAIL_FROM_NAME=\"\${APP_NAME}\"\n\n";
                
                $envContent .= "# PayKassa API Configuration\n";
                $envContent .= "PAYKASSA_MERCHANT_ID=\n";
                $envContent .= "PAYKASSA_API_KEY=\n";
                $envContent .= "PAYKASSA_SECRET_KEY=\n";
                $envContent .= "PAYKASSA_TEST_MODE=true\n\n";
                
                $envContent .= "# Security\n";
                $envContent .= "JWT_SECRET=" . bin2hex(random_bytes(32)) . "\n";
                $envContent .= "SESSION_LIFETIME=120\n";
                $envContent .= "ENCRYPTION_KEY=" . bin2hex(random_bytes(16)) . "\n\n";
                
                $envContent .= "# Mining Simulation Settings\n";
                $envContent .= "DEFAULT_MINING_RATE=0.01\n";
                $envContent .= "MIN_WITHDRAWAL=10\n";
                $envContent .= "WITHDRAWAL_FEE=0.5\n";
                $envContent .= "REFERRAL_COMMISSION=0.05\n\n";
                
                $envContent .= "# File Storage\n";
                $envContent .= "STORAGE_DRIVER=local\n";
                
                file_put_contents(ROOT_PATH . '/.env', $envContent);
                
                // 2. Create database schema
                $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['name']}";
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ];
                $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], $options);
                
                // Import schema.sql
                $schemaFile = ROOT_PATH . '/database/schema.sql';
                if (file_exists($schemaFile)) {
                    $sql = file_get_contents($schemaFile);
                    $pdo->exec($sql);
                }
                
                // 3. Create admin user
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, status, email_verified_at, referral_code, created_at, updated_at) VALUES (?, ?, ?, 'admin', 'active', NOW(), ?, NOW(), NOW())");
                $referralCode = substr(md5($adminConfig['username']), 0, 10);
                $stmt->execute([$adminConfig['username'], $adminConfig['email'], $adminConfig['password'], $referralCode]);
                
                // 4. Initialize default settings
                // This can be handled by the Setting model in the application
                
                $success = true;
                
                // Clean up session
                unset($_SESSION['db_config']);
                unset($_SESSION['admin_config']);
                
                // Redirect to final step
                header('Location: index.php?step=6');
                exit;
            } catch (Exception $e) {
                $errors[] = 'Installation failed: ' . $e->getMessage();
            }
            break;
    }
}

// Start output buffering
ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tronmining Installation</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f5f7fa;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        header {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo {
            font-size: 28px;
            font-weight: bold;
            color: #2c3e50;
        }
        .steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 15px;
        }
        .step {
            text-align: center;
            font-size: 14px;
        }
        .step-number {
            display: inline-block;
            width: 30px;
            height: 30px;
            line-height: 30px;
            text-align: center;
            background-color: #e0e0e0;
            border-radius: 50%;
            margin-bottom: 5px;
        }
        .step.active .step-number {
            background-color: #3498db;
            color: white;
        }
        .step.completed .step-number {
            background-color: #2ecc71;
            color: white;
        }
        .content {
            background-color: white;
            padding: 30px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        h1, h2, h3 {
            color: #2c3e50;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"],
        input[type="password"],
        input[type="email"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        button, .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 16px;
        }
        button:hover, .btn:hover {
            background-color: #2980b9;
        }
        .btn-success {
            background-color: #2ecc71;
        }
        .btn-success:hover {
            background-color: #27ae60;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table th, table td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        .requirement-status {
            font-weight: bold;
        }
        .requirement-status.success {
            color: #2ecc71;
        }
        .requirement-status.warning {
            color: #f39c12;
        }
        .requirement-status.danger {
            color: #e74c3c;
        }
        .buttons {
            margin-top: 20px;
            display: flex;
            justify-content: space-between;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="logo">Tronmining</div>
            <p>HYIP System Installation</p>
        </header>
        
        <div class="steps">
            <?php foreach ($steps as $stepNum => $stepName): ?>
                <div class="step <?php echo $stepNum == $currentStep ? 'active' : ($stepNum < $currentStep ? 'completed' : ''); ?>">
                    <div class="step-number"><?php echo $stepNum; ?></div>
                    <div class="step-name"><?php echo $stepName; ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="content">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <p>Operation completed successfully!</p>
                </div>
            <?php endif; ?>
            
            <?php
            switch ($currentStep) {
                case 1: // Welcome
                    ?>
                    <h2>Welcome to Tronmining Installation</h2>
                    <p>Thank you for choosing Tronmining HYIP System. This installation wizard will guide you through the setup process.</p>
                    <p>Before you begin, please make sure you have the following information ready:</p>
                    <ul>
                        <li>Database credentials (host, username, password, database name)</li>
                        <li>Administrator account details</li>
                    </ul>
                    <p>Also ensure that your server meets the system requirements which will be checked in the next step.</p>
                    
                    <div class="buttons">
                        <div></div>
                        <a href="index.php?step=2" class="btn">Continue</a>
                    </div>
                    <?php
                    break;
                
                case 2: // Requirements Check
                    // Check PHP version
                    $phpVersion = phpversion();
                    $phpVersionOk = version_compare($phpVersion, '7.4.0', '>=');
                    
                    // Check extensions
                    $extensions = [
                        'pdo' => extension_loaded('pdo'),
                        'pdo_mysql' => extension_loaded('pdo_mysql'),
                        'gd' => extension_loaded('gd'),
                        'curl' => extension_loaded('curl'),
                        'mbstring' => extension_loaded('mbstring'),
                        'json' => extension_loaded('json'),
                        'openssl' => extension_loaded('openssl')
                    ];
                    
                    // Check directory permissions
                    $dirs = [
                        '/' => is_writable(ROOT_PATH),
                        '/public/uploads' => is_writable(ROOT_PATH . '/public/uploads'),
                        '/app/views' => is_writable(ROOT_PATH . '/app/views')
                    ];
                    
                    // Check if all requirements are met
                    $allRequirementsMet = $phpVersionOk && !in_array(false, $extensions, true) && !in_array(false, $dirs, true);
                    ?>
                    <h2>System Requirements Check</h2>
                    <p>Checking if your server meets the system requirements.</p>
                    
                    <h3>PHP Version</h3>
                    <table>
                        <tr>
                            <td>PHP Version (7.4.0 or higher)</td>
                            <td><?php echo $phpVersion; ?></td>
                            <td>
                                <span class="requirement-status <?php echo $phpVersionOk ? 'success' : 'danger'; ?>">
                                    <?php echo $phpVersionOk ? 'OK' : 'Error'; ?>
                                </span>
                            </td>
                        </tr>
                    </table>
                    
                    <h3>Required PHP Extensions</h3>
                    <table>
                        <?php foreach ($extensions as $extension => $loaded): ?>
                            <tr>
                                <td><?php echo $extension; ?></td>
                                <td>
                                    <span class="requirement-status <?php echo $loaded ? 'success' : 'danger'; ?>">
                                        <?php echo $loaded ? 'Loaded' : 'Not Loaded'; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                    
                    <h3>Directory Permissions</h3>
                    <table>
                        <?php foreach ($dirs as $dir => $writable): ?>
                            <tr>
                                <td><?php echo $dir; ?></td>
                                <td>
                                    <span class="requirement-status <?php echo $writable ? 'success' : 'danger'; ?>">
                                        <?php echo $writable ? 'Writable' : 'Not Writable'; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                    
                    <div class="buttons">
                        <a href="index.php?step=1" class="btn">Back</a>
                        <?php if ($allRequirementsMet): ?>
                            <a href="index.php?step=3" class="btn">Continue</a>
                        <?php else: ?>
                            <p class="requirement-status danger">Please fix the above issues before continuing.</p>
                        <?php endif; ?>
                    </div>
                    <?php
                    break;
                
                case 3: // Database Configuration
                    ?>
                    <h2>Database Configuration</h2>
                    <p>Please enter your database connection details:</p>
                    
                    <form method="post" action="index.php?step=3">
                        <div class="form-group">
                            <label for="db_host">Database Host:</label>
                            <input type="text" id="db_host" name="db_host" value="localhost" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="db_port">Database Port:</label>
                            <input type="text" id="db_port" name="db_port" value="3306" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="db_name">Database Name:</label>
                            <input type="text" id="db_name" name="db_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="db_user">Database Username:</label>
                            <input type="text" id="db_user" name="db_user" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="db_pass">Database Password:</label>
                            <input type="password" id="db_pass" name="db_pass">
                        </div>
                        
                        <div class="buttons">
                            <a href="index.php?step=2" class="btn">Back</a>
                            <button type="submit" class="btn">Test Connection & Continue</button>
                        </div>
                    </form>
                    <?php
                    break;
                
                case 4: // Administrator Setup
                    ?>
                    <h2>Administrator Account Setup</h2>
                    <p>Create your administrator account:</p>
                    
                    <form method="post" action="index.php?step=4">
                        <div class="form-group">
                            <label for="admin_user">Username:</label>
                            <input type="text" id="admin_user" name="admin_user" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="admin_email">Email Address:</label>
                            <input type="email" id="admin_email" name="admin_email" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="admin_pass">Password:</label>
                            <input type="password" id="admin_pass" name="admin_pass" required>
                            <small>Password must be at least 8 characters long.</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="admin_pass_confirm">Confirm Password:</label>
                            <input type="password" id="admin_pass_confirm" name="admin_pass_confirm" required>
                        </div>
                        
                        <div class="buttons">
                            <a href="index.php?step=3" class="btn">Back</a>
                            <button type="submit" class="btn">Continue</button>
                        </div>
                    </form>
                    <?php
                    break;
                
                case 5: // Installation
                    ?>
                    <h2>Installing Tronmining</h2>
                    <p>Please wait while the system is being installed...</p>
                    
                    <form method="post" action="index.php?step=5">
                        <div class="buttons">
                            <a href="index.php?step=4" class="btn">Back</a>
                            <button type="submit" class="btn">Install Now</button>
                        </div>
                    </form>
                    <?php
                    break;
                
                case 6: // Finished
                    ?>
                    <h2>Installation Completed!</h2>
                    <p>Congratulations! Tronmining has been installed successfully.</p>
                    
                    <p><strong>Important Security Note:</strong> For security reasons, please delete the installation directory.</p>
                    
                    <p>You can now:</p>
                    <ul>
                        <li>Log in to your <a href="../admin/login">Admin Dashboard</a></li>
                        <li>Visit your <a href="../">Website Homepage</a></li>
                    </ul>
                    
                    <div class="buttons">
                        <div></div>
                        <a href="../" class="btn btn-success">Go to Homepage</a>
                    </div>
                    <?php
                    break;
            }
            ?>
        </div>
    </div>
</body>
</html>
<?php
// End output buffering and send output
ob_end_flush();
?> 