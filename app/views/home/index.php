<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title ?? 'Tronmining'; ?></title>
    <meta name="description" content="<?php echo $description ?? 'Plataforma de mineração de criptomoedas'; ?>">
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
        }
        nav ul {
            display: flex;
            list-style: none;
        }
        nav ul li {
            margin-left: 20px;
        }
        nav ul li a {
            text-decoration: none;
            color: #3498db;
        }
        .hero {
            text-align: center;
            padding: 80px 0;
            background-color: #f9f9f9;
            border-radius: 10px;
            margin-bottom: 40px;
        }
        .hero h1 {
            font-size: 42px;
            margin-bottom: 20px;
            color: #2c3e50;
        }
        .hero p {
            font-size: 18px;
            color: #7f8c8d;
            margin-bottom: 30px;
        }
        .btn {
            display: inline-block;
            background-color: #3498db;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        .btn:hover {
            background-color: #2980b9;
        }
        .features {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            margin: 40px 0;
        }
        .feature {
            flex-basis: 30%;
            background-color: #fff;
            padding: 30px;
            border-radius: 5px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .feature h3 {
            font-size: 20px;
            margin-bottom: 15px;
            color: #2c3e50;
        }
        footer {
            text-align: center;
            padding: 20px 0;
            margin-top: 40px;
            border-top: 1px solid #eee;
            color: #7f8c8d;
        }
    </style>
</head>
<body>
    <header>
        <div class="logo">Tronmining</div>
        <nav>
            <ul>
                <li><a href="/">Home</a></li>
                <li><a href="/packages">Pacotes</a></li>
                <li><a href="/login">Login</a></li>
                <li><a href="/register">Registro</a></li>
            </ul>
        </nav>
    </header>

    <main>
        <section class="hero">
            <h1><?php echo $title ?? 'Bem-vindo ao Tronmining'; ?></h1>
            <p><?php echo $description ?? 'Plataforma de mineração de criptomoedas'; ?></p>
            <a href="/register" class="btn">Começar Agora</a>
        </section>

        <section class="features">
            <div class="feature">
                <h3>Mineração Simplificada</h3>
                <p>Comece a minerar criptomoedas sem conhecimento técnico ou equipamentos caros.</p>
            </div>
            <div class="feature">
                <h3>Ganhos Diários</h3>
                <p>Receba pagamentos diários diretamente em sua carteira digital.</p>
            </div>
            <div class="feature">
                <h3>Suporte 24/7</h3>
                <p>Nossa equipe está sempre disponível para ajudar com qualquer dúvida.</p>
            </div>
        </section>
    </main>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> Tronmining. Todos os direitos reservados.</p>
    </footer>

    <script src="/public/assets/js/main.js"></script>
</body>
</html> 