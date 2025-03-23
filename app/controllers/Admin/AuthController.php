<?php
namespace App\Controllers\Admin;

use App\Core\Controller;

/**
 * Admin Auth Controller
 * 
 * Gerencia autenticação e controle de acesso na área administrativa
 */
class AuthController extends Controller
{
    /**
     * Exibe formulário de login administrativo
     */
    public function loginForm()
    {
        // Dados para a view
        $data = [
            'title' => 'Login Admin - Tronmining',
            'description' => 'Acesse o painel administrativo'
        ];
        
        // Verificar se deve usar uma view ou apenas exibir mensagem
        if (file_exists(APP_PATH . '/views/admin/auth/login.php')) {
            $this->view('admin/auth/login', $data);
        } else {
            echo '<h1>' . $data['title'] . '</h1>';
            echo '<p>' . $data['description'] . '</p>';
            echo '<p>Formulário de login administrativo não disponível. Sistema em construção.</p>';
            echo '<p><a href="/">Voltar para Home</a></p>';
        }
    }
    
    /**
     * Processa o login administrativo
     */
    public function login()
    {
        echo '<h1>Processamento de Login Administrativo</h1>';
        echo '<p>Função não implementada. Sistema em construção.</p>';
        echo '<p><a href="/">Voltar para Home</a></p>';
    }
    
    /**
     * Efetua logout administrativo
     */
    public function logout()
    {
        echo '<h1>Logout Administrativo</h1>';
        echo '<p>Função não implementada. Sistema em construção.</p>';
        echo '<p><a href="/">Voltar para Home</a></p>';
    }
} 