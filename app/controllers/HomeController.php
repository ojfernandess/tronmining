<?php
namespace App\Controllers;

use App\Core\Controller;

/**
 * Home Controller
 * 
 * Controla as ações da página inicial
 */
class HomeController extends Controller
{
    /**
     * Método inicial - Exibe a página inicial
     */
    public function index()
    {
        // Dados para a view
        $data = [
            'title' => 'Bem-vindo ao Tronmining',
            'description' => 'Plataforma de mineração de criptomoedas'
        ];
        
        // Verificar se deve usar uma view ou apenas exibir mensagem
        if (file_exists(APP_PATH . '/views/home/index.php')) {
            $this->view('home/index', $data);
        } else {
            echo '<h1>' . $data['title'] . '</h1>';
            echo '<p>' . $data['description'] . '</p>';
            echo '<p>Sistema em funcionamento!</p>';
            echo '<p>Você pode acessar o <a href="/admin">painel administrativo</a> ou <a href="/login">fazer login</a>.</p>';
        }
    }
} 