<?php
namespace App\Controllers\Admin;

use App\Core\Controller;

/**
 * Admin Dashboard Controller
 * 
 * Controla as ações do dashboard administrativo
 */
class DashboardController extends Controller
{
    /**
     * Método inicial - Exibe o dashboard administrativo
     */
    public function index()
    {
        // Dados para a view
        $data = [
            'title' => 'Dashboard Admin - Tronmining',
            'description' => 'Painel administrativo'
        ];
        
        // Verificar se deve usar uma view ou apenas exibir mensagem
        if (file_exists(APP_PATH . '/views/admin/dashboard/index.php')) {
            $this->view('admin/dashboard/index', $data);
        } else {
            echo '<h1>' . $data['title'] . '</h1>';
            echo '<p>' . $data['description'] . '</p>';
            echo '<p>Painel administrativo em construção.</p>';
            echo '<p><a href="/">Voltar para Home</a></p>';
        }
    }
} 