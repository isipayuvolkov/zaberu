<?php
require_once 'db.php';

$successMessage = '';
$errorMessage = '';

// Загружаем пункты выдачи из базы для выпадающего списка
$pointsStmt = $pdo->query("SELECT * FROM pickup_points");
$pickupPoints = $pointsStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $companyName = trim($_POST['company_name'] ?? '');
    $weight = floatval($_POST['weight'] ?? 1.00);
    $recipientName = trim($_POST['recipient_name'] ?? '');
    $recipientPhone = trim($_POST['recipient_phone'] ?? '');
    $recipientEmail = trim($_POST['recipient_email'] ?? '');
    $city = trim($_POST['city'] ?? 'Москва');
    $pointId = intval($_POST['point_id'] ?? 1);

    if (empty($companyName) || empty($recipientName) || empty($recipientPhone)) {
        $errorMessage = 'Пожалуйста, заполните все обязательные поля формы.';
    } else {
        // Генерируем уникальный трек-номер
        $trackNumber = 'ZAB-' . rand(100000, 999999);
        
        // Берем первого партнера из базы для привязки
        $partnerId = 1;

        // Проверяем или добавляем клиента в таблицу users по телефону
        $userStmt = $pdo->prepare("SELECT user_id FROM users WHERE phone = ?");
        $userStmt->execute([$recipientPhone]);
        $user = $userStmt->fetch();

        if ($user) {
            $userId = $user['user_id'];
        } else {
            $insUser = $pdo->prepare("INSERT INTO users (full_name, email, phone, password_hash, city, address, role) VALUES (?, ?, ?, '12345', ?, 'Самовывоз ПВЗ', 'client')");
            $insUser->execute([$recipientName, $recipientEmail, $recipientPhone, $city]);
            $userId = $pdo->lastInsertId();
        }

        // Вставляем отправление в таблицу parcels
        $stmt = $pdo->prepare("
            INSERT INTO parcels (track_number, weight, status, storage_days, partner_id, user_id, point_id) 
            VALUES (?, ?, 'registered', 7, ?, ?, ?)
        ");
        
        if ($stmt->execute([$trackNumber, $weight, $partnerId, $userId, $pointId])) {
            $successMessage = "Заказ успешно подтвержден! Сгенерирован трек-номер: {$trackNumber}";
        } else {
            $errorMessage = 'Ошибка при сохранении заказа в базу данных.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Заберу — Оформление заказа</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="page-body">
    <header class="site-header">
        <div class="header-container">
            <div class="header-logo-wrap">
                <img class="logo-text" src="img/logo.png" alt="Заберу">
            </div>
            <nav class="header-nav">
                <a href="index.html" class="nav-link">Главная</a>
                <a href="catalog.html" class="nav-link">Тарифы</a>
                <a href="tracking.php" class="nav-link">Отследить</a>
                <a href="delivery.html" class="nav-link">Доставка</a>
                <a href="contacts.html" class="nav-link">Контакты</a>
            </nav>
            <div>
                <a href="check_auth.php" class="btn-primary">Личный кабинет</a>
            </div>
        </div>
    </header>

    <main class="main-content">
        <section class="page-header-section">
            <div class="hero-container">
                <span class="badge">Для юридических лиц и партнеров</span>
                <h1 class="hero-title">Оформление <span class="text-orange">заказа</span></h1>
                <p class="hero-desc">Интеграция для интернет-магазинов, выбор пункта выдачи и генерация трек-номера отправления.</p>
            </div>
        </section>

        <section class="checkout-section">
            <div class="hero-container">
                <?php if ($successMessage): ?>
                    <div style="margin-bottom: 2rem; padding: 1.5rem; background: #dcfce7; color: #166534; border-radius: 1rem; font-weight: 700; text-align: center; font-size: 1.2rem;">
                        <?= htmlspecialchars($successMessage) ?>
                        <div style="margin-top: 1rem;">
                            <a href="tracking.php" class="tariff-btn active-btn" style="display:inline-block; text-decoration:none; padding: 0.75rem 2rem;">Перейти к отслеживанию</a>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($errorMessage): ?>
                    <div style="margin-bottom: 2rem; padding: 1rem; background: #fee2e2; color: #991b1b; border-radius: 0.75rem; font-weight: 600; text-align: center;">
                        <?= htmlspecialchars($errorMessage) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="checkout.php" class="checkout-grid">
                    <div class="delivery-card">
                        <h3 class="tariff-title mb-4">1. Выбор пункта выдачи</h3>
                        <div class="calc-weight-wrap">
                            <label class="calc-label">Пункт выдачи заказов (ПВЗ)</label>
                            <select name="point_id" class="calc-select">
                                <?php foreach ($pickupPoints as $pt): ?>
                                    <option value="<?= $pt['point_id'] ?>">
                                        <?= htmlspecialchars($pt['city'] . ', ' . $pt['address']) ?> (Режим: <?= htmlspecialchars($pt['work_hours']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="map-placeholder" style="height: 220px; margin-top: 1rem;">
                            <iframe 
                                src="https://yandex.ru/map-widget/v1/?um=constructor%3A7a192c7328906324901fbc65bc116f1a8e52dbcc5441e8e25d2c1c69df464a2f&source=constructor" 
                                width="100%" 
                                height="100%" 
                                frameborder="0" 
                                style="border-radius: 1rem; border: none;">
                            </iframe>
                        </div>
                    </div>

                    <div class="delivery-card">
                        <h3 class="tariff-title mb-4">2. Данные отправителя и отправка</h3>
                        
                        <!-- Поле города с автоопределением -->
                        <div class="calc-weight-wrap">
                            <label class="calc-label">Город получения</label>
                            <input type="text" id="cityInput" name="city" placeholder="Определение города..." class="calc-input" required>
                            <small id="cityAutoLabel" style="color: var(--brand-orange); display: none; margin-top: 0.25rem; font-size: 0.8rem;"></small>
                        </div>

                        <div class="calc-weight-wrap">
                            <label class="calc-label">Название магазина / Юрлицо</label>
                            <input type="text" name="company_name" placeholder="ИП «Интернет-Магазин»" class="calc-input" required>
                        </div>
                        <div class="calc-weight-wrap">
                            <label class="calc-label">Вес отправления (кг)</label>
                            <input type="number" name="weight" value="1" min="0.1" max="50" step="0.1" class="calc-input" required>
                        </div>
                        <div class="calc-weight-wrap">
                            <label class="calc-label">ФИО получателя</label>
                            <input type="text" name="recipient_name" placeholder="Иванов Иван Иванович" class="calc-input" required>
                        </div>
                        <div class="calc-weight-wrap">
                            <label class="calc-label">Номер телефона</label>
                            <input type="text" name="recipient_phone" placeholder="+7 (999) 000-00-00" class="calc-input" required>
                        </div>
                        <div class="calc-weight-wrap">
                            <label class="calc-label">Email для уведомлений</label>
                            <input type="email" name="recipient_email" placeholder="client@example.com" class="calc-input">
                        </div>
                        <button type="submit" class="tariff-btn active-btn w-full mt-4">Подтвердить и сгенерировать трек-номер</button>
                    </div>
                </form>
            </div>
        </section>
    </main>

    <footer id="contacts" class="site-footer">
        <div class="footer-container">
            <div class="footer-col">
                <div class="footer-logo-wrap">
                    <img class="footer-title" src="img/logo-foot.png" alt="Заберу">
                </div>
                <p class="footer-copy">© 2026 Сервис выдачи заказов «Заберу». Все права защищены.</p>
            </div>
            <div class="footer-col">
                <h4 class="footer-heading">Навигация</h4>
                <div class="footer-nav-list">
                    <a href="index.html" class="footer-link">Главная страница</a>
                    <a href="catalog.html" class="footer-link">Каталог услуг / Тарифы</a>
                    <a href="tracking.php" class="footer-link">Отслеживание посылки</a>
                </div>
            </div>
            <div class="footer-col">
                <h4 class="footer-heading">Аккаунт и Заказы</h4>
                <div class="footer-nav-list">
                    <a href="check_auth.php" class="footer-link">Авторизация / Личный кабинет</a>
                    <a href="register.php" class="footer-link">Регистрация</a>
                    <a href="checkout.php" class="footer-link">Оформление заказа</a>
                </div>
            </div>
            <div class="footer-col">
                <h4 class="footer-heading">Информация</h4>
                <div class="footer-nav-list">
                    <a href="delivery.html" class="footer-link">Доставка и оплата</a>
                    <a href="contacts.html" class="footer-link">Контакты</a>
                    <a href="faq.html" class="footer-link">Частые вопросы (FAQ) / Отзывы</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Подключаем скрипт геолокации и запускаем автоопределение -->
    <script src="js/locationapi.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            LocationAPI.autoFillCity('cityInput', 'cityAutoLabel');
        });
    </script>
</body>
</html>