<?php
session_start();
require_once 'db.php';

// Проверка прав администратора
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$stmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$currentUser = $stmt->fetch();

if (!$currentUser || $currentUser['role'] !== 'admin') {
    die("<h1>403 Доступ запрещен</h1><p>У вас нет прав администратора.</p><a href='profile.php'>В личный кабинет</a>");
}

$message = '';
$action = $_GET['action'] ?? 'overview';
$table = $_GET['table'] ?? '';
$viewName = $_GET['view'] ?? '';

// Первичные ключи для таблиц сервиса «Заберу»
$primaryKeys = [
    'partners' => 'partner_id',
    'users' => 'user_id',
    'pickup_points' => 'point_id',
    'parcels' => 'parcel_id',
    'order_items' => 'item_id',
    'reviews' => 'review_id'
];

// Список разрешенных представлений (VIEW)
$allowedViews = [
    'v_active_parcels',
    'v_pickup_point_stats',
    'v_point_ratings',
    'v_partner_activity',
    'v_parcel_contents_details'
];

// Обработка удаления
if ($action === 'delete' && array_key_exists($table, $primaryKeys)) {
    $id = $_GET['id'] ?? 0;
    $pk = $primaryKeys[$table];
    $del = $pdo->prepare("DELETE FROM `$table` WHERE `$pk` = ?");
    if ($del->execute([$id])) {
        $message = "Запись успешно удалена из таблицы $table.";
    }
    $action = 'manage';
}

// Обработка добавления / редактирования
if ($_SERVER['REQUEST_METHOD'] === 'POST' && array_key_exists($table, $primaryKeys)) {
    $pk = $primaryKeys[$table];
    $id = $_POST['id'] ?? 0;
    
    $colsStmt = $pdo->query("DESCRIBE `$table`");
    $columns = $colsStmt->fetchAll();
    
    $fields = [];
    $values = [];
    
    foreach ($columns as $col) {
        $fieldName = $col['Field'];
        if ($fieldName === $pk) {
            continue; 
        }
        if (isset($_POST[$fieldName])) {
            $fields[] = "`$fieldName` = ?";
            $values[] = $_POST[$fieldName];
        }
    }

    if (empty($id)) {
        // INSERT
        $insertCols = [];
        $insertPlaceholders = [];
        $insertValues = [];
        foreach ($columns as $col) {
            $fieldName = $col['Field'];
            if ($fieldName === $pk) continue;
            if (isset($_POST[$fieldName])) {
                $insertCols[] = "`$fieldName`";
                $insertPlaceholders[] = "?";
                $insertValues[] = $_POST[$fieldName];
            }
        }
        $sql = "INSERT INTO `$table` (" . implode(', ', $insertCols) . ") VALUES (" . implode(', ', $insertPlaceholders) . ")";
        $stmtIns = $pdo->prepare($sql);
        if ($stmtIns->execute($insertValues)) {
            $message = "Запись успешно добавлена в таблицу $table.";
        }
    } else {
        // UPDATE
        $values[] = $id;
        $sql = "UPDATE `$table` SET " . implode(', ', $fields) . " WHERE `$pk` = ?";
        $stmtUpd = $pdo->prepare($sql);
        if ($stmtUpd->execute($values)) {
            $message = "Запись успешно обновлена в таблице $table.";
        }
    }
    $action = 'manage';
}

// Статистика под базу данных «Заберу»
$totalSum = $pdo->query("SELECT SUM(price * quantity) AS s FROM order_items")->fetch()['s'] ?? 0;
$totalParcels = $pdo->query("SELECT COUNT(*) FROM parcels")->fetchColumn();
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalPoints = $pdo->query("SELECT COUNT(*) FROM pickup_points")->fetchColumn();

// Количество записей по каждой из 6 таблиц
$counts = [
    'parcels' => $totalParcels,
    'users' => $totalUsers,
    'partners' => $pdo->query("SELECT COUNT(*) FROM partners")->fetchColumn(),
    'pickup_points' => $totalPoints,
    'order_items' => $pdo->query("SELECT COUNT(*) FROM order_items")->fetchColumn(),
    'reviews' => $pdo->query("SELECT COUNT(*) FROM reviews")->fetchColumn(),
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Заберу — Панель администратора</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        body { background-color: #f8fafc; font-family: 'Manrope', sans-serif; }
        .admin-header {
            background-color: var(--dark-bg, #1e293b);
            color: white;
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 1rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .admin-header h1 { font-size: 1.8rem; margin: 0; font-weight: 700; color: white; }
        .admin-header-btns { display: flex; gap: 1rem; }
        .admin-btn { background: transparent; border: 1px solid white; color: white; padding: 0.5rem 1.25rem; border-radius: 0.5rem; text-decoration: none; font-weight: 600; transition: 0.2s; }
        .admin-btn:hover { background: var(--brand-orange, #ff6b00); border-color: var(--brand-orange); }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }
        .stat-value { font-size: 1.8rem; font-weight: 800; color: #1e293b; margin-bottom: 0.25rem; }
        .stat-label { font-size: 0.9rem; color: #64748b; font-weight: 500; }

        .section-heading { font-size: 1.5rem; font-weight: 700; color: #1e293b; margin-bottom: 1rem; }

        .overview-tables-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .overview-table-card {
            background: white;
            padding: 1.25rem 1.5rem;
            border-radius: 1rem;
            border: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            text-decoration: none;
            color: inherit;
            transition: 0.2s;
        }
        .overview-table-card:hover { border-color: var(--brand-orange, #ff6b00); box-shadow: 0 4px 12px rgba(255,107,0,0.08); }
        .overview-table-name { font-size: 1.1rem; font-weight: 700; color: #1e293b; }
        .overview-table-count { font-size: 1.2rem; font-weight: 800; color: var(--brand-orange, #ff6b00); }

        .content-box { background: white; padding: 2rem; border-radius: 1rem; border: 1px solid #e2e8f0; margin-bottom: 2rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid #e2e8f0; font-size: 0.9rem; }
        th { background: #f8fafc; font-weight: 700; color: #475569; }
        .action-link { margin-right: 0.75rem; font-weight: 600; text-decoration: none; }
        .action-edit { color: #2563eb; }
        .action-del { color: #dc2626; }
        
        .form-control { width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 0.5rem; margin-bottom: 1rem; font-family: inherit; }
    </style>
</head>
<body class="page-body" style="padding: 2rem 0;">
    <div class="hero-container" style="max-width: 1300px;">
        
        <!-- Шапка панели управления -->
        <div class="admin-header">
            <h1>Админ-панель</h1>
            <div class="admin-header-btns">
                <a href="index.html" class="admin-btn">На сайт</a>
                <a href="logout.php" class="admin-btn">Выйти</a>
            </div>
        </div>

        <?php if ($message): ?>
            <div style="margin-bottom: 1.5rem; padding: 1rem; background: #dcfce7; color: #166534; border-radius: 0.75rem; font-weight: 600;">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($action === 'overview'): ?>
            <!-- Блоки статистики под проект «Заберу» -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?= number_format($totalSum, 0, ',', ' ') ?> ₽</div>
                    <div class="stat-label">Сумма товаров в заказах</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $totalParcels ?></div>
                    <div class="stat-label">Отправлений (Посылок)</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $totalUsers ?></div>
                    <div class="stat-label">Пользователей</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $totalPoints ?></div>
                    <div class="stat-label">Пунктов выдачи (ПВЗ)</div>
                </div>
            </div>

            <!-- Обзор таблиц -->
            <h2 class="section-heading">Обзор таблиц базы данных</h2>
            <div class="overview-tables-grid">
                <?php foreach ($counts as $tblName => $cnt): ?>
                    <a href="admin.php?action=manage&table=<?= $tblName ?>" class="overview-table-card">
                        <span class="overview-table-name"><?= ucfirst(str_replace('_', ' ', $tblName)) ?></span>
                        <span class="overview-table-count"><?= $cnt ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Аналитические представления (VIEW) с возможностью клика и просмотра -->
            <h2 class="section-heading" style="margin-top: 2rem;">Аналитические представления (VIEW)</h2>
            <div class="overview-tables-grid">
                <?php foreach ($allowedViews as $v): ?>
                    <a href="admin.php?action=view&view=<?= $v ?>" class="overview-table-card">
                        <div>
                            <div class="overview-table-name"><?= htmlspecialchars($v) ?></div>
                            <div class="stat-label" style="margin-top: 0.25rem;">Нажмите, чтобы открыть данные</div>
                        </div>
                        <span style="color: var(--brand-orange, #ff6b00); font-weight: bold;">→</span>
                    </a>
                <?php endforeach; ?>
            </div>

        <?php elseif ($action === 'view' && in_array($viewName, $allowedViews)): ?>
            <div style="margin-bottom: 1.5rem;">
                <a href="admin.php" class="tariff-btn" style="text-decoration: none; padding: 0.5rem 1rem; font-size: 0.9rem;">← Назад к обзору</a>
            </div>

            <div class="content-box">
                <h2 class="section-heading">Просмотр представления: <b><?= htmlspecialchars($viewName) ?></b></h2>
                <div style="overflow-x: auto;">
                    <?php
                    $viewRows = $pdo->query("SELECT * FROM `$viewName`")->fetchAll();
                    ?>
                    <?php if (empty($viewRows)): ?>
                        <p style="color: #64748b; padding: 1rem 0;">Представление не содержит данных.</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <?php foreach (array_keys($viewRows[0]) as $col): ?>
                                        <th><?= htmlspecialchars($col) ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($viewRows as $row): ?>
                                    <tr>
                                        <?php foreach ($row as $val): ?>
                                            <td><?= htmlspecialchars($val ?? 'NULL') ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($action === 'manage' && array_key_exists($table, $primaryKeys)): ?>
            <div style="margin-bottom: 1.5rem;">
                <a href="admin.php" class="tariff-btn" style="text-decoration: none; padding: 0.5rem 1rem; font-size: 0.9rem;">← Назад к обзору</a>
                <a href="admin.php?action=add&table=<?= $table ?>" class="tariff-btn active-btn" style="text-decoration: none; padding: 0.5rem 1rem; font-size: 0.9rem; margin-left: 1rem;">+ Добавить запись</a>
            </div>

            <div class="content-box">
                <h2 class="section-heading">Управление таблицей: <b><?= htmlspecialchars($table) ?></b></h2>
                <div style="overflow-x: auto;">
                    <?php
                    $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll();
                    $pk = $primaryKeys[$table];
                    ?>
                    <?php if (empty($rows)): ?>
                        <p style="color: #64748b; padding: 1rem 0;">В таблице пока нет записей.</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <?php foreach (array_keys($rows[0]) as $col): ?>
                                        <th><?= htmlspecialchars($col) ?></th>
                                    <?php endforeach; ?>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $row): ?>
                                    <tr>
                                        <?php foreach ($row as $val): ?>
                                            <td><?= htmlspecialchars($val ?? 'NULL') ?></td>
                                        <?php endforeach; ?>
                                        <td>
                                            <a href="admin.php?action=edit&table=<?= $table ?>&id=<?= $row[$pk] ?>" class="action-link action-edit">Изменить</a>
                                            <a href="admin.php?action=delete&table=<?= $table ?>&id=<?= $row[$pk] ?>" class="action-link action-del" onclick="return confirm('Удалить эту запись?')">Удалить</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif (($action === 'add' || $action === 'edit') && array_key_exists($table, $primaryKeys)): ?>
            <div style="margin-bottom: 1.5rem;">
                <a href="admin.php?action=manage&table=<?= $table ?>" class="tariff-btn" style="text-decoration: none; padding: 0.5rem 1rem; font-size: 0.9rem;">← К списку таблицы</a>
            </div>

            <div class="content-box" style="max-width: 700px; margin: 0 auto;">
                <h2 class="section-heading"><?= $action === 'add' ? 'Добавление записи в ' . $table : 'Редактирование записи в ' . $table ?></h2>
                
                <?php
                $pk = $primaryKeys[$table];
                $rowData = [];
                if ($action === 'edit' && isset($_GET['id'])) {
                    $id = $_GET['id'];
                    $st = $pdo->prepare("SELECT * FROM `$table` WHERE `$pk` = ?");
                    $st->execute([$id]);
                    $rowData = $st->fetch() ?: [];
                }
                
                $cols = $pdo->query("DESCRIBE `$table`")->fetchAll();
                ?>
                
                <form method="POST" action="admin.php?action=manage&table=<?= $table ?>">
                    <input type="hidden" name="id" value="<?= htmlspecialchars($rowData[$pk] ?? '') ?>">
                    
                    <?php foreach ($cols as $col): ?>
                        <?php 
                        $fieldName = $col['Field'];
                        if ($fieldName === $pk && $action === 'add') continue; 
                        $val = $rowData[$fieldName] ?? '';
                        ?>
                        <div class="calc-weight-wrap" style="margin-bottom: 1.25rem;">
                            <label class="calc-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600;"><?= htmlspecialchars($fieldName) ?> <span style="font-weight: 400; color: #64748b; font-size: 0.85rem;">(<?= $col['Type'] ?>)</span></label>
                            <input type="text" name="<?= htmlspecialchars($fieldName) ?>" value="<?= htmlspecialchars($val) ?>" class="form-control" <?= $fieldName === $pk ? 'readonly style="background:#f1f5f9;"' : '' ?>>
                        </div>
                    <?php endforeach; ?>
                    
                    <button type="submit" class="tariff-btn active-btn w-full" style="margin-top: 1rem;">Сохранить</button>
                </form>
            </div>
        <?php endif; ?>

    </div>
</body>
</html>