<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title ?? 'Tronmining - Plataforma de Mineração'; ?></title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            background-color: #f4f7fa;
            color: #333;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        header {
            background-color: #2d3748;
            color: white;
            padding: 1rem 0;
            text-align: center;
        }
        .hero {
            background-color: #4299e1;
            color: white;
            padding: 3rem 0;
            text-align: center;
            margin-bottom: 2rem;
        }
        .hero h1 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        .hero p {
            font-size: 1.2rem;
            max-width: 800px;
            margin: 0 auto;
        }
        .features {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            margin-bottom: 2rem;
        }
        .feature {
            flex: 0 0 30%;
            background-color: white;
            padding: 1.5rem;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }
        .feature h3 {
            font-size: 1.3rem;
            margin-top: 0;
            color: #3182ce;
        }
        .btn {
            display: inline-block;
            background-color: #3182ce;
            color: white;
            padding: 0.8rem 2rem;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        .btn:hover {
            background-color: #2c5282;
        }
        footer {
            background-color: #2d3748;
            color: white;
            text-align: center;
            padding: 1.5rem 0;
            margin-top: 2rem;
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <h2>TRONMINING</h2>
            <nav>
                <a href="/">Home</a> |
                <a href="/login">Login</a> |
                <a href="/register">Registro</a> |
                <a href="/about">Sobre</a>
            </nav>
        </div>
    </header>

    <section class="hero">
        <div class="container">
            <h1><?php echo $title ?? 'Bem-vindo ao Tronmining'; ?></h1>
            <p><?php echo $description ?? 'A plataforma de mineração de criptomoedas mais confiável do mercado.'; ?></p>
            <p>Comece a minerar hoje mesmo e obtenha retornos diários!</p>
            <a href="/register" class="btn">Comece Agora</a>
        </div>
    </section>

    <div class="container">
        <section class="features">
            <div class="feature">
                <h3>Mineração Simplificada</h3>
                <p>Sem necessidade de equipamentos caros ou conhecimentos técnicos. Nossa plataforma faz todo o trabalho para você.</p>
            </div>
            <div class="feature">
                <h3>Retornos Diários</h3>
                <p>Receba recompensas diárias baseadas no seu poder de mineração adquirido.</p>
            </div>
            <div class="feature">
                <h3>Segurança Garantida</h3>
                <p>Todas as transações são registradas na blockchain, garantindo total transparência e segurança.</p>
            </div>
            <div class="feature">
                <h3>Programa de Referência</h3>
                <p>Convide amigos e ganhe comissões sobre os investimentos deles. Sistema multinível!</p>
            </div>
            <div class="feature">
                <h3>Suporte 24/7</h3>
                <p>Nossa equipe de suporte está disponível a qualquer momento para responder suas dúvidas.</p>
            </div>
            <div class="feature">
                <h3>Múltiplas Criptomoedas</h3>
                <p>Minere várias criptomoedas populares, incluindo Bitcoin, Ethereum, Tron e muito mais.</p>
            </div>
        </section>

        <section class="cta">
            <h2>Pronto para começar sua jornada na mineração?</h2>
            <p>Crie sua conta gratuita hoje e comece a minerar em minutos.</p>
            <a href="/register" class="btn">Criar Conta</a>
        </section>
    </div>

    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Tronmining. Todos os direitos reservados.</p>
        </div>
    </footer>
</body>
</html> 