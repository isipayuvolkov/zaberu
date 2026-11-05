<?php
session_start();
require_once 'db.php';

$successMsg = '';
$errorMsg = '';

// Загружаем список пунктов выдачи для привязки отзыва к ПВЗ (согласно структуре БД)
$pointsStmt = $pdo->query("SELECT * FROM pickup_points");
$pickupPoints = $pointsStmt->fetchAll();

// Обработка отправки нового отзыва
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $guestName = trim($_POST['guest_name'] ?? '');
    $rating = intval($_POST['rating'] ?? 5);
    $reviewText = trim($_POST['review_text'] ?? '');
    $pointId = intval($_POST['point_id'] ?? 1);

    if (empty($guestName) || empty($reviewText)) {
        $errorMsg = 'Пожалуйста, заполните все поля отзыва.';
    } else {
        $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;

        $stmt = $pdo->prepare("
            INSERT INTO reviews (review_text, rating, user_id, point_id, review_date) 
            VALUES (?, ?, ?, ?, CURRENT_DATE)
        ");
        
        $fullReviewText = "Автор: " . $guestName . ". " . $reviewText;

        if ($stmt->execute([$fullReviewText, $rating, $userId, $pointId])) {
            $successMsg = 'Спасибо за ваш отзыв! Он успешно добавлен в базу данных.';
        } else {
            $errorMsg = 'Ошибка при сохранении отзыва.';
        }
    }
}

// Загружаем отзывы из базы данных
$reviewsStmt = $pdo->query("
    SELECT r.*, pp.city, pp.address AS point_address 
    FROM reviews r
    JOIN pickup_points pp ON r.point_id = pp.point_id
    ORDER BY r.review_id DESC
");
$allReviews = $reviewsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Заберу — Частые вопросы и отзывы</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="page-body">
    <header class="site-header">
        <div class="header-container">
            <div class="header-logo-wrap">
                <img class="logo-text" src="img\logo.png" alt="Заберу">
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
                <span class="badge">Помощь и отзывы</span>
                <h1 class="hero-title">Частые вопросы и <span class="text-orange">отзывы</span></h1>
                <p class="hero-desc">Ответы на популярные вопросы о работе сервиса и отзывы наших клиентов.</p>
            </div>
        </section>

        <section class="faq-section">
            <div class="hero-container">
                <div class="faq-grid">
                    <div class="delivery-card">
                        <h3 class="tariff-title">Сколько хранится заказ в пункте выдачи?</h3>
                        <p class="tariff-desc">Срок бесплатного хранения заказа составляет 7 дней с момента поступления в пункт выдачи. Вы можете продлить срок хранения в личном кабинете.</p>
                    </div>
                    <div class="delivery-card">
                        <h3 class="tariff-title">Что делать, если товар поврежден?</h3>
                        <p class="tariff-desc">При получении осмотрите упаковку и товар. Если обнаружены повреждения, сотрудник пункта выдачи составит акт о повреждении для возврата средств.</p>
                    </div>
                    <div class="delivery-card">
                        <h3 class="tariff-title">Нужен ли паспорт для получения?</h3>
                        <p class="tariff-desc">Для большинства заказов достаточно назвать только уникальный код из СМС-сообщения. Паспорт может потребоваться для получения ценных отправлений.</p>
                    </div>
                </div>

                <!-- Блок вывода отзывов из базы данных -->
                <div style="margin-top: 3rem;">
                    <h2 class="section-title text-center mb-6">Отзывы клиентов из базы данных</h2>
                    <?php if (empty($allReviews)): ?>
                        <p class="text-center text-gray">Пока нет отзывов.</p>
                    <?php else: ?>
                        <div style="display: grid; gap: 1rem; margin-bottom: 3rem;">
                            <?php foreach ($allReviews as $rev): ?>
                                <div class="delivery-card" style="padding: 1.5rem;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                        <b style="color: var(--brand-orange);">Оценка: <?= htmlspecialchars($rev['rating']) ?> / 5 ⭐</b>
                                        <span class="text-sm text-gray"><?= htmlspecialchars($rev['review_date']) ?> | ПВЗ: <?= htmlspecialchars($rev['city'] . ', ' . $rev['point_address']) ?></span>
                                    </div>
                                    <p><?= nl2br(htmlspecialchars($rev['review_text'])) ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Форма отправки отзыва с рейтингом и ПВЗ -->
                <div class="review-box-container">
                    <h2 class="section-title text-center mb-6">Оставить отзыв о работе пункта</h2>

                    <?php if ($successMsg): ?>
                        <div style="margin-bottom: 1.5rem; padding: 1rem; background: #dcfce7; color: #166534; border-radius: 0.75rem; font-weight: 600; text-align: center;">
                            <?= htmlspecialchars($successMsg) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($errorMsg): ?>
                        <div style="margin-bottom: 1.5rem; padding: 1rem; background: #fee2e2; color: #991b1b; border-radius: 0.75rem; font-weight: 600; text-align: center;">
                            <?= htmlspecialchars($errorMsg) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="faq.php" class="feedback-form max-w-xl mx-auto bg-white p-6 rounded-2xl border border-gray-200">
                        <input type="hidden" name="submit_review" value="1">
                        
                        <div class="calc-weight-wrap mb-3">
                            <label class="calc-label">Ваше имя</label>
                            <input type="text" name="guest_name" placeholder="Иван" class="calc-input" required>
                        </div>

                        <div class="calc-weight-wrap mb-3">
                            <label class="calc-label">Выберите пункт выдачи (ПВЗ)</label>
                            <select name="point_id" class="calc-select" style="width: 100%; padding: 0.75rem; border-radius: 0.5rem; border: 1px solid #ccc;">
                                <?php foreach ($pickupPoints as $pt): ?>
                                    <option value="<?= $pt['point_id'] ?>">
                                        <?= htmlspecialchars($pt['city'] . ', ' . $pt['address']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="calc-weight-wrap mb-3">
                            <label class="calc-label">Оценка (рейтинг от 1 до 5)</label>
                            <select name="rating" class="calc-select" style="width: 100%; padding: 0.75rem; border-radius: 0.5rem; border: 1px solid #ccc;" required>
                                <option value="5">⭐⭐⭐⭐⭐ (5 - Отлично)</option>
                                <option value="4">⭐⭐⭐⭐ (4 - Хорошо)</option>
                                <option value="3">⭐⭐⭐ (3 - Нормально)</option>
                                <option value="2">⭐⭐ (2 - Плохо)</option>
                                <option value="1">⭐ (1 - Ужасно)</option>
                            </select>
                        </div>

                        <div class="calc-weight-wrap mb-3">
                            <label class="calc-label">Ваш отзыв</label>
                            <textarea name="review_text" placeholder="Ваш отзыв..." class="calc-input" rows="4" style="resize: vertical;" required></textarea>
                        </div>

                        <button type="submit" class="tariff-btn active-btn w-full">Отправить отзыв</button>
                    </form>
                </div>
            </div>
        </section>
    </main>

    <footer id="contacts" class="site-footer">
        <div class="footer-container">
            <div class="footer-col">
                <div class="footer-logo-wrap">
                    <img class="footer-title" src="img\logo-foot.png" alt="Заберу">
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
                    <a href="faq.php" class="footer-link">Частые вопросы (FAQ) / Отзывы</a>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>