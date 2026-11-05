<?php
session_start();
require_once 'db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($login) || empty($password)) {
        $error = 'Пожалуйста, заполните все поля.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR phone = ?");
        $stmt->execute([$login, $login]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['user_name'] = $user['full_name'];
            header('Location: profile.php');
            exit;
        } else {
            $error = 'Неверный email/телефон или пароль.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Заберу — Вход в личный кабинет</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="page-body auth-body">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <img class="logo-icon mb-3" src="img\logo-box.png" alt="Заберу">
                <h2 class="auth-title">Вход в личный кабинет</h2>
                <p class="auth-subtitle">Введите учетные данные для доступа</p>
            </div>

            <div class="auth-tabs">
                <button class="auth-tab active">Вход</button>
                <a href="register.php" class="auth-tab text-center" style="text-decoration: none;">Регистрация</a>
            </div>

            <?php if ($error): ?>
                <div style="margin-top: 1rem; padding: 0.75rem; background: #fee2e2; color: #991b1b; border-radius: 0.5rem; font-size: 0.875rem; text-align: center; font-weight: 600;">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" class="auth-form-content">
                <div class="calc-weight-wrap">
                    <label class="calc-label">Номер телефона или Email</label>
                    <input type="text" name="login" placeholder="+7 (999) 000-00-00" class="calc-input" required>
                </div>
                <div class="calc-weight-wrap">
                    <label class="calc-label">Пароль</label>
                    <input type="password" name="password" placeholder="••••••••" class="calc-input" required>
                </div>
                <button type="submit" class="tariff-btn active-btn w-full">Войти</button>
            </form>

            <div class="auth-footer-link">
                <a href="index.html" class="footer-link">← Вернуться на главную</a>
            </div>
        </div>
    </div>
</body>
</html>