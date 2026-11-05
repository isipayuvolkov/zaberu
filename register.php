<?php
session_start();
require_once 'db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $email = trim($_POST['email'] ?? 'user@example.com');

    if (empty($name) || empty($phone) || empty($password)) {
        $error = 'Пожалуйста, заполните все обязательные поля.';
    } elseif (mb_strlen($password) < 6) {
        $error = 'Пароль должен содержать не менее 6 символов.';
    } else {
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE phone = ?");
        $stmt->execute([$phone]);
        if ($stmt->fetch()) {
            $error = 'Пользователь с таким телефоном уже зарегистрирован.';
        } else {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $ins = $pdo->prepare("INSERT INTO users (full_name, email, phone, password_hash, city, address, role) VALUES (?, ?, ?, ?, 'Москва', 'Самовывоз', 'client')");
            if ($ins->execute([$name, $email, $phone, $passwordHash])) {
                $_SESSION['user_id'] = $pdo->lastInsertId();
                $_SESSION['user_name'] = $name;
                header('Location: profile.php');
                exit;
            } else {
                $error = 'Ошибка при регистрации.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Заберу — Регистрация аккаунта</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="page-body auth-body">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <img class="logo-icon mb-3" src="img\logo-box.png" alt="Заберу">
                <h2 class="auth-title">Регистрация в сервисе</h2>
                <p class="auth-subtitle">Создайте аккаунт для управления заказами</p>
            </div>

            <div class="auth-tabs">
                <a href="check_auth.php" class="auth-tab">Вход</a>
                <button class="auth-tab active">Регистрация</button>
            </div>

            <?php if ($error): ?>
                <div style="margin-top: 1rem; padding: 0.75rem; background: #fee2e2; color: #991b1b; border-radius: 0.5rem; font-size: 0.875rem; text-align: center; font-weight: 600;">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="register.php" class="auth-form-content">
                <div class="calc-weight-wrap">
                    <label class="calc-label">Ваше имя</label>
                    <input type="text" name="name" placeholder="Иван" class="calc-input" required>
                </div>
                <div class="calc-weight-wrap">
                    <label class="calc-label">Номер телефона</label>
                    <input type="text" name="phone" placeholder="+7 (999) 000-00-00" class="calc-input" required>
                </div>
                <div class="calc-weight-wrap">
                    <label class="calc-label">Email</label>
                    <input type="email" name="email" placeholder="ivan@example.com" class="calc-input">
                </div>
                <div class="calc-weight-wrap">
                    <label class="calc-label">Придумайте пароль (мин. 6 символов)</label>
                    <input type="password" name="password" placeholder="••••••••" class="calc-input" minlength="6" required>
                </div>
                <button type="submit" class="tariff-btn active-btn w-full">Зарегистрироваться</button>
            </form>

            <div class="auth-footer-link">
                <a href="index.html" class="footer-link">← Вернуться на главную</a>
            </div>
        </div>
    </div>
</body>
</html>