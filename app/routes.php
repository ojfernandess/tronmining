<?php
/**
 * Routes configuration for Tronmining
 * 
 * This file defines all the routes for the application
 */

use App\Core\Router;

$router = $app->getRouter();

// Home/Landing page
$router->get('/', 'HomeController@index');
$router->get('/home', 'HomeController@index');

// Authentication routes
$router->get('/login', 'AuthController@loginForm');
$router->post('/login', 'AuthController@login');
$router->get('/register', 'AuthController@registerForm');
$router->post('/register', 'AuthController@register');
$router->get('/logout', 'AuthController@logout');
$router->get('/forgot-password', 'AuthController@forgotPasswordForm');
$router->post('/forgot-password', 'AuthController@forgotPassword');
$router->get('/reset-password/{token}', 'AuthController@resetPasswordForm');
$router->post('/reset-password', 'AuthController@resetPassword');
$router->get('/verify-email/{token}', 'AuthController@verifyEmail');

// User dashboard routes
$router->get('/dashboard', 'DashboardController@index');
$router->get('/profile', 'UserController@profile');
$router->post('/profile/update', 'UserController@updateProfile');
$router->get('/security', 'UserController@security');
$router->post('/security/password', 'UserController@updatePassword');
$router->post('/security/2fa/enable', 'UserController@enable2FA');
$router->post('/security/2fa/disable', 'UserController@disable2FA');
$router->get('/referrals', 'ReferralController@index');
$router->get('/transactions', 'TransactionController@index');

// Mining package routes
$router->get('/packages', 'MiningPackageController@index');
$router->get('/package/{id}', 'MiningPackageController@view');
$router->post('/package/purchase', 'MiningPackageController@purchase');
$router->get('/mining-power', 'MiningPowerController@index');

// Wallet routes
$router->get('/wallets', 'WalletController@index');
$router->post('/wallet/add', 'WalletController@add');
$router->post('/wallet/delete', 'WalletController@delete');
$router->post('/wallet/primary', 'WalletController@setPrimary');

// Transaction routes
$router->get('/deposit', 'DepositController@index');
$router->post('/deposit/process', 'DepositController@process');
$router->get('/deposit/confirm/{id}', 'DepositController@confirm');
$router->get('/deposit/cancel/{id}', 'DepositController@cancel');
$router->get('/deposit/success/{id}', 'DepositController@success');
$router->get('/withdraw', 'WithdrawController@index');
$router->post('/withdraw/process', 'WithdrawController@process');

// KYC routes
$router->get('/kyc', 'KycController@index');
$router->post('/kyc/submit', 'KycController@submit');
$router->get('/kyc/status', 'KycController@status');

// Support ticket routes
$router->get('/support', 'SupportController@index');
$router->get('/support/ticket/create', 'SupportController@create');
$router->post('/support/ticket/store', 'SupportController@store');
$router->get('/support/ticket/{id}', 'SupportController@view');
$router->post('/support/ticket/reply', 'SupportController@reply');
$router->get('/support/ticket/close/{id}', 'SupportController@close');
$router->get('/support/ticket/reopen/{id}', 'SupportController@reopen');

// Admin routes
$router->get('/admin', 'Admin\DashboardController@index');
$router->get('/admin/login', 'Admin\AuthController@loginForm');
$router->post('/admin/login', 'Admin\AuthController@login');
$router->get('/admin/logout', 'Admin\AuthController@logout');

// Admin users management
$router->get('/admin/users', 'Admin\UserController@index');
$router->get('/admin/user/{id}', 'Admin\UserController@view');
$router->post('/admin/user/update/{id}', 'Admin\UserController@update');
$router->get('/admin/user/toggle-status/{id}', 'Admin\UserController@toggleStatus');

// Admin mining packages management
$router->get('/admin/packages', 'Admin\PackageController@index');
$router->get('/admin/package/create', 'Admin\PackageController@create');
$router->post('/admin/package/store', 'Admin\PackageController@store');
$router->get('/admin/package/edit/{id}', 'Admin\PackageController@edit');
$router->post('/admin/package/update/{id}', 'Admin\PackageController@update');
$router->get('/admin/package/toggle-status/{id}', 'Admin\PackageController@toggleStatus');

// Admin transactions management
$router->get('/admin/transactions', 'Admin\TransactionController@index');
$router->get('/admin/transaction/{id}', 'Admin\TransactionController@view');
$router->post('/admin/transaction/update-status/{id}', 'Admin\TransactionController@updateStatus');

// Admin settings management
$router->get('/admin/settings', 'Admin\SettingController@index');
$router->post('/admin/settings/update', 'Admin\SettingController@update');

// Admin payment methods management
$router->get('/admin/payment-methods', 'Admin\PaymentMethodController@index');
$router->get('/admin/payment-method/edit/{id}', 'Admin\PaymentMethodController@edit');
$router->post('/admin/payment-method/update/{id}', 'Admin\PaymentMethodController@update');
$router->get('/admin/payment-method/toggle-status/{id}', 'Admin\PaymentMethodController@toggleStatus');

// Admin KYC management
$router->get('/admin/kyc', 'Admin\KycController@index');
$router->get('/admin/kyc/{id}', 'Admin\KycController@view');
$router->post('/admin/kyc/approve/{id}', 'Admin\KycController@approve');
$router->post('/admin/kyc/reject/{id}', 'Admin\KycController@reject');

// Admin support ticket management
$router->get('/admin/support', 'Admin\SupportController@index');
$router->get('/admin/support/ticket/{id}', 'Admin\SupportController@view');
$router->post('/admin/support/ticket/reply', 'Admin\SupportController@reply');
$router->get('/admin/support/ticket/close/{id}', 'Admin\SupportController@close');
$router->get('/admin/support/ticket/assign/{id}', 'Admin\SupportController@assign');

// Fallback route for 404 errors
$router->setErrorHandler(function() {
    http_response_code(404);
    include APP_PATH . '/views/errors/404.php';
}); 