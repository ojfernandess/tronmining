<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 - Erro Interno do Servidor</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            color: #333;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            text-align: center;
        }
        .error-container {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            padding: 40px;
            max-width: 700px;
            width: 90%;
        }
        h1 {
            font-size: 72px;
            margin: 0;
            color: #e74c3c;
        }
        h2 {
            margin-top: 0;
            margin-bottom: 20px;
        }
        p {
            font-size: 16px;
            line-height: 1.5;
            margin-bottom: 15px;
        }
        .btn {
            display: inline-block;
            background-color: #3498db;
            color: #fff;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 5px;
            transition: background-color 0.3s;
            margin-top: 15px;
        }
        .btn:hover {
            background-color: #2980b9;
        }
        .debug-info {
            margin-top: 20px;
            text-align: left;
            background-color: #f5f5f5;
            padding: 15px;
            border-radius: 5px;
            font-size: 14px;
            overflow: auto;
        }
        code {
            font-family: monospace;
            display: block;
            white-space: pre-wrap;
            margin-bottom: 10px;
            padding: 10px;
            background-color: #eee;
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <h1>500</h1>
        <h2>Erro Interno do Servidor</h2>
        <p>Ocorreu um erro ao processar sua solicitação. Nossa equipe técnica foi notificada e está trabalhando para resolver o problema.</p>
        <a href="/" class="btn">Voltar para Home</a>
        
        <?php if (defined('DEBUG_MODE') && DEBUG_MODE === true): ?>
        <div class="debug-info">
            <h3>Informações de Diagnóstico:</h3>
            <?php if (isset($error_message)): ?>
            <p><strong>Erro:</strong> <?php echo htmlspecialchars($error_message); ?></p>
            <?php endif; ?>
            
            <?php if (isset($error_file)): ?>
            <p><strong>Arquivo:</strong> <?php echo htmlspecialchars($error_file); ?></p>
            <?php endif; ?>
            
            <?php if (isset($error_line)): ?>
            <p><strong>Linha:</strong> <?php echo (int)$error_line; ?></p>
            <?php endif; ?>
            
            <?php if (isset($error_trace) && is_string($error_trace)): ?>
            <p><strong>Stack Trace:</strong></p>
            <code><?php echo htmlspecialchars($error_trace); ?></code>
            <?php endif; ?>
            
            <p><strong>URI:</strong> <?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? ''); ?></p>
            <p><strong>Método:</strong> <?php echo htmlspecialchars($_SERVER['REQUEST_METHOD'] ?? ''); ?></p>
        </div>
        <?php endif; ?>
    </div>
</body>
</html> 