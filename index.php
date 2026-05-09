<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['user'])) {
    header("Location: pages/authorization.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Главная</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: "Segoe UI", Arial, sans-serif;
            background: #0f172a;
            color: #e2e8f0;
        }
        .box {
            text-align: center;
            max-width: 620px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(148, 163, 184, 0.25);
            border-radius: 14px;
            padding: 24px;
        }
        a { color: #93c5fd; }
    </style>
</head>
<body>
    <div class="box">
        <h1>Главная страница</h1>
        <a href="pages/authorization.php?logout=1">Выйти</a>
    </div>
</body>
</html>