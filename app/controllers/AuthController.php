<?php
namespace App\Controllers;

use App\Core\Controller;

/**
 * Auth Controller
 * 
 * Gerencia autenticação e registro de usuários
 */
class AuthController extends Controller
{
    /**
     * Exibe formulário de login
     */
    public function loginForm()
    {
        // Dados para a view
        $data = [
            'title' => 'Login - Tronmining',
            'description' => 'Acesse sua conta'
        ];
        
        // Verificar se deve usar uma view ou apenas exibir mensagem
        if (file_exists(APP_PATH . '/views/auth/login.php')) {
            $this->view('auth/login', $data);
        } else {
            echo '<h1>' . $data['title'] . '</h1>';
            echo '<p>' . $data['description'] . '</p>';
            echo '<p>Formulário de login não disponível. Sistema em construção.</p>';
            echo '<p><a href="/">Voltar para Home</a></p>';
        }
    }
    
    /**
     * Processa o login
     */
    public function login()
    {
        echo '<h1>Processamento de Login</h1>';
        echo '<p>Função não implementada. Sistema em construção.</p>';
        echo '<p><a href="/">Voltar para Home</a></p>';
    }
    
    /**
     * Exibe formulário de registro
     */
    public function registerForm()
    {
        // Dados para a view
        $data = [
            'title' => 'Registro - Tronmining',
            'description' => 'Crie sua conta'
        ];
        
        // Verificar se deve usar uma view ou apenas exibir mensagem
        if (file_exists(APP_PATH . '/views/auth/register.php')) {
            $this->view('auth/register', $data);
        } else {
            echo '<h1>' . $data['title'] . '</h1>';
            echo '<p>' . $data['description'] . '</p>';
            echo '<p>Formulário de registro não disponível. Sistema em construção.</p>';
            echo '<p><a href="/">Voltar para Home</a></p>';
        }
    }
    
    /**
     * Processa o registro
     */
    public function register()
    {
        echo '<h1>Processamento de Registro</h1>';
        echo '<p>Função não implementada. Sistema em construção.</p>';
        echo '<p><a href="/">Voltar para Home</a></p>';
    }
    
    /**
     * Efetua logout
     */
    public function logout()
    {
        echo '<h1>Logout</h1>';
        echo '<p>Função não implementada. Sistema em construção.</p>';
        echo '<p><a href="/">Voltar para Home</a></p>';
    }
    
    /**
     * Exibe formulário de recuperação de senha
     */
    public function forgotPasswordForm()
    {
        // Dados para a view
        $data = [
            'title' => 'Recuperar Senha - Tronmining',
            'description' => 'Recupere sua senha'
        ];
        
        // Verificar se deve usar uma view ou apenas exibir mensagem
        if (file_exists(APP_PATH . '/views/auth/forgot-password.php')) {
            $this->view('auth/forgot-password', $data);
        } else {
            echo '<h1>' . $data['title'] . '</h1>';
            echo '<p>' . $data['description'] . '</p>';
            echo '<p>Formulário de recuperação de senha não disponível. Sistema em construção.</p>';
            echo '<p><a href="/">Voltar para Home</a></p>';
        }
    }
    
    /**
     * Processa recuperação de senha
     */
    public function forgotPassword()
    {
        echo '<h1>Processamento de Recuperação de Senha</h1>';
        echo '<p>Função não implementada. Sistema em construção.</p>';
        echo '<p><a href="/">Voltar para Home</a></p>';
    }
    
    /**
     * Exibe formulário de redefinição de senha
     */
    public function resetPasswordForm()
    {
        // Dados para a view
        $data = [
            'title' => 'Redefinir Senha - Tronmining',
            'description' => 'Defina uma nova senha',
            'token' => $this->params['token'] ?? ''
        ];
        
        // Verificar se deve usar uma view ou apenas exibir mensagem
        if (file_exists(APP_PATH . '/views/auth/reset-password.php')) {
            $this->view('auth/reset-password', $data);
        } else {
            echo '<h1>' . $data['title'] . '</h1>';
            echo '<p>' . $data['description'] . '</p>';
            echo '<p>Token: ' . $data['token'] . '</p>';
            echo '<p>Formulário de redefinição de senha não disponível. Sistema em construção.</p>';
            echo '<p><a href="/">Voltar para Home</a></p>';
        }
    }
    
    /**
     * Processa redefinição de senha
     */
    public function resetPassword()
    {
        echo '<h1>Processamento de Redefinição de Senha</h1>';
        echo '<p>Função não implementada. Sistema em construção.</p>';
        echo '<p><a href="/">Voltar para Home</a></p>';
    }
    
    /**
     * Verifica email
     */
    public function verifyEmail()
    {
        $token = $this->params['token'] ?? '';
        
        echo '<h1>Verificação de Email</h1>';
        echo '<p>Token: ' . $token . '</p>';
        echo '<p>Função não implementada. Sistema em construção.</p>';
        echo '<p><a href="/">Voltar para Home</a></p>';
    }
} 