<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId =$_SESSION['user_id'];

$stmt =$pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$currentUser =$stmt->fetch();

$parcelsStmt =$pdo->prepare("
    SELECT p.*, pp.address AS point_address, pt.company_name 
    FROM parcels p
    JOIN pickup_points pp ON p.point_id = pp.point_id
    JOIN partners pt ON p.partner_id = pt.partner_id
    WHERE p.user_id = ?
");
$parcelsStmt->execute([$userId]);
$userParcels =$parcelsStmt->fetchAll();

$successMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $newName = trim($_POST['full_name'] ?? '');
    $newPhone = trim($_POST['phone'] ?? '');
    $newEmail = trim($_POST['email'] ?? '');

    $upd =$pdo->prepare("UPDATE users SET full_name = ?, phone = ?, email = ? WHERE user_id = ?");
    if ($upd->execute([$newName,$newPhone, $newEmail,$userId])) {
        $_SESSION['user_name'] =$newName;
        $successMsg = 'Изменения успешно сохранены!';
        $stmt->execute([$userId]);
        $currentUser =$stmt->fetch();
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Заберу — Личный кабинет</title>
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
                <a href="logout.php" class="btn-primary" style="background-color: #343435;">Выйти</a>
            </div>
        </div>
    </header>

    <main class="main-content">
        <section class="page-header-section">
            <div class="hero-container">
                <span class="badge">Управление аккаунтом</span>
                <h1 class="hero-title">Личный <span class="text-orange">кабинет</span></h1>
                <p class="hero-desc">История ваших отправлений, полученных заказов и персональные настройки.</p>
            </div>
        </section>

        <section class="cabinet-section">
            <div class="hero-container">
                <?php if ($successMsg): ?>
                    <div style="margin-bottom: 2rem; padding: 1rem; background: #dcfce7; color: #166534; border-radius: 0.75rem; font-weight: 600; text-align: center;">
                        <?= htmlspecialchars($successMsg) ?>
                    </div>
                <?php endif; ?>

                <div class="cabinet-grid">
                    <!-- Сайдбар профиля -->
                    <div class="delivery-card" style="height: fit-content;">
                        <div class="profile-header-card">
                            <div class="profile-avatar"><?= mb_strtoupper(mb_substr($currentUser['full_name'], 0, 1)) ?></div>
                            <div>
                                <h3 class="tariff-title" style="margin-bottom: 0.25rem;"><?= htmlspecialchars($currentUser['full_name']) ?></h3>
                                <p class="text-sm text-gray"><?= htmlspecialchars($currentUser['phone']) ?></p>
                                <?php if ($currentUser['role'] === 'admin'): ?>
                                    <span style="display: inline-block; margin-top: 0.25rem; background: #ffedd5; color: #c2410c; padding: 0.2rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; font-weight: bold;">Администратор</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="profile-menu">
                            <a href="#orders" class="profile-menu-item active">📦 Мои отправления (<?= count($userParcels) ?>)</a>
                            <a href="#settings" class="profile-menu-item">⚙️ Настройки профиля</a>
                            
                            <!-- ПРОВЕРКА РОЛИ АДМИНА -->
                            <?php if ($currentUser['role'] === 'admin'): ?>
                                <a href="admin.php" class="profile-menu-item" style="color: #c2410c; font-weight: bold;">🛠️ Панель администратора</a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Основной контент кабинета -->
                    <div class="cabinet-content-area">
                        <!-- Вкладка: Мои отправления -->
                        <div class="delivery-card mb-4" id="orders">
                            <h3 class="tariff-title mb-4">История отправлений и заказов</h3>
                            <?php if (empty($userParcels)): ?>
                                <p class="text-gray">У вас пока нет отправлений.</p>
                            <?php else: ?>
                                <div class="orders-list">
                                    <?php foreach ($userParcels as$parcel): ?>
                                        <div class="order-item-card">
                                            <div class="order-info">
                                                <span class="track-number-label">Трек-номер: <b><?= htmlspecialchars($parcel['track_number']) ?></b></span>
                                                <h4 class="timeline-title"><?= htmlspecialchars($parcel['company_name']) ?></h4>
                                                <p class="timeline-desc">Пункт выдачи: <?= htmlspecialchars($parcel['point_address']) ?></p>
                                            </div>
                                            <div class="order-status-badge <?= $parcel['status'] === 'received' ? 'completed' : 'active' ?>">
                                                <?= htmlspecialchars($parcel['status']) ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Вкладка: Настройки профиля -->
                        <div class="delivery-card" id="settings">
                            <h3 class="tariff-title mb-4">Настройки профиля</h3>
                            <form method="POST" action="profile.php">
                                <input type="hidden" name="update_profile" value="1">
                                <div class="calc-weight-wrap">
                                    <label class="calc-label">Имя</label>
                                    <input type="text" name="full_name" value="<?= htmlspecialchars($currentUser['full_name']) ?>" class="calc-input" required>
                                </div>
                                <div class="calc-weight-wrap">
                                    <label class="calc-label">Номер телефона</label>
                                    <input type="text" name="phone" value="<?= htmlspecialchars($currentUser['phone']) ?>" class="calc-input" required>
                                </div>
                                <div class="calc-weight-wrap">
                                    <label class="calc-label">Email</label>
                                    <input type="email" name="email" value="<?= htmlspecialchars($currentUser['email']) ?>" class="calc-input">
                                </div>
                                <button type="submit" class="tariff-btn active-btn w-full mt-2">Сохранить изменения</button>
                            </form>
                        </div>
                    </div>
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