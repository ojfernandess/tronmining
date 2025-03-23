<?php
/**
 * Routes configuration for Tronmining
 * 
 * This file defines all the routes for the application
 */

use App\Core\Router;

error_log("Carregando rotas da aplicação em " . __FILE__);

// Rota para a página inicial - Deve corresponder a '/'
Router::get('/', function() {
    echo '<h1>Bem-vindo ao Tronmining</h1>';
    echo '<p>O sistema está funcionando corretamente.</p>';
    echo '<p>Você pode começar a configurar o sistema ou acessar o <a href="/admin">painel administrativo</a>.</p>';
});

// Home/Landing page - rota alternativa
Router::get('/home', function() {
    echo '<h1>Bem-vindo ao Tronmining</h1>';
    echo '<p>O sistema está funcionando corretamente.</p>';
    echo '<p>Você pode começar a configurar o sistema ou acessar o <a href="/admin">painel administrativo</a>.</p>';
});

// Authentication routes
Router::get('/login', 'AuthController@loginForm');
Router::post('/login', 'AuthController@login');
Router::get('/register', 'AuthController@registerForm');
Router::post('/register', 'AuthController@register');
Router::get('/logout', 'AuthController@logout');
Router::get('/forgot-password', 'AuthController@forgotPasswordForm');
Router::post('/forgot-password', 'AuthController@forgotPassword');
Router::get('/reset-password/{token}', 'AuthController@resetPasswordForm');
Router::post('/reset-password', 'AuthController@resetPassword');
Router::get('/verify-email/{token}', 'AuthController@verifyEmail');

// User dashboard routes
Router::get('/dashboard', 'DashboardController@index');
Router::get('/profile', 'UserController@profile');
Router::post('/profile/update', 'UserController@updateProfile');
Router::get('/security', 'UserController@security');
Router::post('/security/password', 'UserController@updatePassword');
Router::post('/security/2fa/enable', 'UserController@enable2FA');
Router::post('/security/2fa/disable', 'UserController@disable2FA');
Router::get('/referrals', 'ReferralController@index');
Router::get('/transactions', 'TransactionController@index');

// Mining package routes
Router::get('/packages', 'MiningPackageController@index');
Router::get('/package/{id}', 'MiningPackageController@view');
Router::post('/package/purchase', 'MiningPackageController@purchase');
Router::get('/mining-power', 'MiningPowerController@index');

// Wallet routes
Router::get('/wallets', 'WalletController@index');
Router::post('/wallet/add', 'WalletController@add');
Router::post('/wallet/delete', 'WalletController@delete');
Router::post('/wallet/primary', 'WalletController@setPrimary');

// Transaction routes
Router::get('/deposit', 'DepositController@index');
Router::post('/deposit/process', 'DepositController@process');
Router::get('/deposit/confirm/{id}', 'DepositController@confirm');
Router::get('/deposit/cancel/{id}', 'DepositController@cancel');
Router::get('/deposit/success/{id}', 'DepositController@success');
Router::get('/withdraw', 'WithdrawController@index');
Router::post('/withdraw/process', 'WithdrawController@process');

// KYC routes
Router::get('/kyc', 'KycController@index');
Router::post('/kyc/submit', 'KycController@submit');
Router::get('/kyc/status', 'KycController@status');

// Support ticket routes
Router::get('/support', 'SupportController@index');
Router::get('/support/ticket/create', 'SupportController@create');
Router::post('/support/ticket/store', 'SupportController@store');
Router::get('/support/ticket/{id}', 'SupportController@view');
Router::post('/support/ticket/reply', 'SupportController@reply');
Router::get('/support/ticket/close/{id}', 'SupportController@close');
Router::get('/support/ticket/reopen/{id}', 'SupportController@reopen');

// Admin routes
Router::get('/admin', 'Admin\DashboardController@index');
Router::get('/admin/login', 'Admin\AuthController@loginForm');
Router::post('/admin/login', 'Admin\AuthController@login');
Router::get('/admin/logout', 'Admin\AuthController@logout');

// Admin users management
Router::get('/admin/users', 'Admin\UserController@index');
Router::get('/admin/user/{id}', 'Admin\UserController@view');
Router::post('/admin/user/update/{id}', 'Admin\UserController@update');
Router::get('/admin/user/toggle-status/{id}', 'Admin\UserController@toggleStatus');

// Admin mining packages management
Router::get('/admin/packages', 'Admin\PackageController@index');
Router::get('/admin/package/create', 'Admin\PackageController@create');
Router::post('/admin/package/store', 'Admin\PackageController@store');
Router::get('/admin/package/edit/{id}', 'Admin\PackageController@edit');
Router::post('/admin/package/update/{id}', 'Admin\PackageController@update');
Router::get('/admin/package/toggle-status/{id}', 'Admin\PackageController@toggleStatus');

// Admin transactions management
Router::get('/admin/transactions', 'Admin\TransactionController@index');
Router::get('/admin/transaction/{id}', 'Admin\TransactionController@view');
Router::post('/admin/transaction/update-status/{id}', 'Admin\TransactionController@updateStatus');

// Admin settings management
Router::get('/admin/settings', 'Admin\SettingController@index');
Router::post('/admin/settings/update', 'Admin\SettingController@update');

// Admin payment methods management
Router::get('/admin/payment-methods', 'Admin\PaymentMethodController@index');
Router::get('/admin/payment-method/edit/{id}', 'Admin\PaymentMethodController@edit');
Router::post('/admin/payment-method/update/{id}', 'Admin\PaymentMethodController@update');
Router::get('/admin/payment-method/toggle-status/{id}', 'Admin\PaymentMethodController@toggleStatus');

// Admin KYC management
Router::get('/admin/kyc', 'Admin\KycController@index');
Router::get('/admin/kyc/{id}', 'Admin\KycController@view');
Router::post('/admin/kyc/approve/{id}', 'Admin\KycController@approve');
Router::post('/admin/kyc/reject/{id}', 'Admin\KycController@reject');

// Admin support ticket management
Router::get('/admin/support', 'Admin\SupportController@index');
Router::get('/admin/support/ticket/{id}', 'Admin\SupportController@view');
Router::post('/admin/support/ticket/reply', 'Admin\SupportController@reply');
Router::get('/admin/support/ticket/close/{id}', 'Admin\SupportController@close');
Router::get('/admin/support/ticket/assign/{id}', 'Admin\SupportController@assign');

// Fallback route for 404 errors - não implementado na nossa versão da classe Router
// Vamos usar o notFound padrão
/*
$router->setErrorHandler(function() {
    http_response_code(404);
    include APP_PATH . '/views/errors/404.php';
});
*/

// Log para depuração
error_log("Rotas carregadas: " . print_r(Router::$routes, true)); 