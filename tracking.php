<?php
require_once 'db.php';

$parcel = null;
$error = '';
$trackNumber = '';

if (isset($_GET['track']) && !empty(trim($_GET['track']))) {
    $trackNumber = trim($_GET['track']);
    
    $stmt = $pdo->prepare("
        SELECT p.*, pp.address AS point_address, pp.city AS point_city, pt.company_name 
        FROM parcels p
        JOIN pickup_points pp ON p.point_id = pp.point_id
        JOIN partners pt ON p.partner_id = pt.partner_id
        WHERE p.track_number = ?
    ");
    $stmt->execute([$trackNumber]);
    $parcel = $stmt->fetch();

    if (!$parcel) {
        $error = 'Посылка с таким трек-номером не найдена.';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Заберу — Отслеживание посылки</title>
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
                <a href="catalog.html" class="nav-link active">Тарифы</a>
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
                <span class="badge">Экспресс-поиск</span>
                <h1 class="hero-title">Отслеживание <span class="text-orange">посылки</span></h1>
                <p class="hero-desc">Введите трек-номер вашего отправления, чтобы узнать актуальный статус, местоположение и детали доставки.</p>
            </div>
        </section>

        <section class="tracking-main-section">
            <div class="hero-container">
                <form method="GET" action="tracking.php" class="tracking-search-box" style="display: flex; gap: 1rem;">
                    <input type="text" name="track" value="<?= htmlspecialchars($trackNumber) ?>" placeholder="Введите трек-номер (например: ZAB-987654)" class="track-input-large" style="flex: 1;">
                    <button type="submit" class="btn-dark-large">Найти отправление</button>
                </form>

                <?php if ($error): ?>
                    <div style="margin-top: 1.5rem; padding: 1rem; background: #fee2e2; color: #991b1b; border-radius: 0.75rem; font-weight: 600; text-align: center;">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <?php if ($parcel): ?>
                    <div id="trackingDetailsCard" class="tracking-details-card" style="margin-top: 2rem;">
                        <div class="tracking-status-header">
                            <div>
                                <p class="track-number-label">Трек-номер: <span class="font-bold"><?= htmlspecialchars($parcel['track_number']) ?></span></p>
                                <h2 class="track-status-title">Статус: <?= htmlspecialchars($parcel['status']) ?></h2>
                            </div>
                            <div class="track-badge-status">Активно</div>
                        </div>

                        <div class="tracking-timeline">
                            <div class="timeline-item completed">
                                <div class="timeline-dot">✓</div>
                                <div class="timeline-content">
                                    <p class="timeline-time"><?= htmlspecialchars($parcel['created_date']) ?></p>
                                    <h4 class="timeline-title">Заказ зарегистрирован</h4>
                                    <p class="timeline-desc">Отправление принято от партнера</p>
                                </div>
                            </div>
                            <div class="timeline-item active">
                                <div class="timeline-dot">📦</div>
                                <div class="timeline-content">
                                    <h4 class="timeline-title">Текущий статус</h4>
                                    <p class="timeline-desc">Статус в системе: <b><?= htmlspecialchars($parcel['status']) ?></b></p>
                                </div>
                            </div>
                        </div>

                        <div class="parcel-meta-grid" style="margin-top: 2rem;">
                            <div class="meta-box">
                                <span class="meta-label">Вес отправления</span>
                                <span class="meta-value"><?= htmlspecialchars($parcel['weight']) ?> кг</span>
                            </div>
                            <div class="meta-box">
                                <span class="meta-label">Отправитель</span>
                                <span class="meta-value"><?= htmlspecialchars($parcel['company_name']) ?></span>
                            </div>
                            <div class="meta-box">
                                <span class="meta-label">Пункт выдачи</span>
                                <span class="meta-value"><?= htmlspecialchars($parcel['point_city'] . ', ' . $parcel['point_address']) ?></span>
                            </div>
                            <div class="meta-box">
                                <span class="meta-label">Срок бесплатного хранения</span>
                                <span class="meta-value"><?= htmlspecialchars($parcel['storage_days']) ?> дней</span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>
<br><br><br>
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
                    <a href="faq.php" class="footer-link">Частые вопросы (FAQ) / Отзывы</a>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>