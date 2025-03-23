<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 - Server Error | Tronmining</title>
    <link rel="stylesheet" href="<?php echo getenv('APP_URL'); ?>/public/assets/css/style.css">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f4f6f9;
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
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            max-width: 500px;
            width: 100%;
        }
        .error-code {
            font-size: 80px;
            font-weight: bold;
            color: #e74c3c;
            margin: 0;
        }
        .error-title {
            font-size: 24px;
            margin: 10px 0 20px;
        }
        .error-message {
            color: #666;
            margin-bottom: 30px;
        }
        .home-button {
            display: inline-block;
            background-color: #3498db;
            color: #fff;
            padding: 12px 24px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        .home-button:hover {
            background-color: #2980b9;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <h1 class="error-code">500</h1>
        <h2 class="error-title">Internal Server Error</h2>
        <p class="error-message">
            Oops! Something went wrong on our end. We're working to fix the issue.
            Please try again later or contact support if the problem persists.
        </p>
        <a href="<?php echo getenv('APP_URL'); ?>" class="home-button">Back to Home</a>
    </div>
</body>
</html> 