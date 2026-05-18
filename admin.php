<?php
require_once 'config.php';

// HTTP Basic Auth
$auth_user = $_SERVER['PHP_AUTH_USER'] ?? '';
$auth_pass = $_SERVER['PHP_AUTH_PW'] ?? '';

$stmt = $pdo->prepare("SELECT password_hash FROM admin WHERE username = ?");
$stmt->execute([$auth_user]);
$admin = $stmt->fetch();

if (!$admin || !password_verify($auth_pass, $admin['password_hash'])) {
    header('WWW-Authenticate: Basic realm="Admin Access"');
    header('HTTP/1.0 401 Unauthorized');
    die('Доступ запрещён');
}

// Обработка действий: удаление, редактирование, просмотр статистики
$action = $_GET['action'] ?? '';
$id = (int)($_GET['id'] ?? 0);

if ($action === 'delete' && $id) {
    $pdo->prepare("DELETE FROM applications WHERE id = ?")->execute([$id]);
    header("Location: admin.php");
    exit;
}

if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST' && $id) {
    $fullname = $_POST['fullname'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];
    $birthdate = $_POST['birthdate'];
    $gender = $_POST['gender'];
    $biography = $_POST['biography'];
    $contract = isset($_POST['contract']) ? 1 : 0;
    $languages = $_POST['languages'] ?? [];
    
    $pdo->prepare("UPDATE applications SET fullname=?, phone=?, email=?, birthdate=?, gender=?, biography=?, contract_accepted=? WHERE id=?")
        ->execute([$fullname, $phone, $email, $birthdate, $gender, $biography, $contract, $id]);
    $pdo->prepare("DELETE FROM application_languages WHERE application_id = ?")->execute([$id]);
    $langStmt = $pdo->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (?, (SELECT id FROM programming_languages WHERE name = ?))");
    foreach ($languages as $lang) {
        $langStmt->execute([$id, $lang]);
    }
    header("Location: admin.php");
    exit;
}

// Получение данных для показа
$applications = $pdo->query("SELECT * FROM applications ORDER BY id DESC")->fetchAll();

// Статистика по языкам
$stats = $pdo->query("SELECT pl.name, COUNT(al.language_id) AS cnt 
                       FROM programming_languages pl 
                       LEFT JOIN application_languages al ON pl.id = al.language_id 
                       GROUP BY pl.id")->fetchAll();

?>
<!DOCTYPE html>
<html>
<head><title>Админ-панель</title><style> table, th, td { border:1px solid #ccc; border-collapse: collapse; padding: 8px; } </style></head>
<body>
<h1>Управление заявками</h1>
<h2>Статистика по языкам</h2>
<ul>
<?php foreach ($stats as $row): ?>
    <li><?= h($row['name']) ?>: <?= $row['cnt'] ?> пользователей</li>
<?php endforeach; ?>
</ul>

<h2>Все заявки</h2>
<table>
    <tr><th>ID</th><th>ФИО</th><th>Email</th><th>Действия</th></tr>
    <?php foreach ($applications as $app): ?>
    <tr>
        <td><?= $app['id'] ?></td>
        <td><?= h($app['fullname']) ?></td>
        <td><?= h($app['email']) ?></td>
        <td>
            <a href="admin.php?action=edit_form&id=<?= $app['id'] ?>">Редактировать</a> |
            <a href="admin.php?action=delete&id=<?= $app['id'] ?>" onclick="return confirm('Удалить?')">Удалить</a>
        </td>
    </tr>
    <?php endforeach; ?>
</table>

<?php if ($action === 'edit_form' && $id): 
    $editApp = $pdo->prepare("SELECT * FROM applications WHERE id = ?");
    $editApp->execute([$id]);
    $edit = $editApp->fetch();
    $langs = $pdo->prepare("SELECT pl.name FROM application_languages al JOIN programming_languages pl ON al.language_id = pl.id WHERE al.application_id = ?");
    $langs->execute([$id]);
    $selectedLangs = $langs->fetchAll(PDO::FETCH_COLUMN);
    $allLangs = $pdo->query("SELECT name FROM programming_languages")->fetchAll(PDO::FETCH_COLUMN);
?>
<h2>Редактирование заявки #<?= $id ?></h2>
<form method="post" action="admin.php?action=edit&id=<?= $id ?>">
    <label>ФИО: <input type="text" name="fullname" value="<?= h($edit['fullname']) ?>" required></label><br>
    <label>Телефон: <input type="tel" name="phone" value="<?= h($edit['phone']) ?>" required></label><br>
    <label>Email: <input type="email" name="email" value="<?= h($edit['email']) ?>" required></label><br>
    <label>Дата рождения: <input type="date" name="birthdate" value="<?= $edit['birthdate'] ?>" required></label><br>
    <label>Пол: 
        <select name="gender">
            <option value="male" <?= $edit['gender']=='male' ? 'selected' : '' ?>>Мужской</option>
            <option value="female" <?= $edit['gender']=='female' ? 'selected' : '' ?>>Женский</option>
            <option value="other" <?= $edit['gender']=='other' ? 'selected' : '' ?>>Другой</option>
        </select>
    </label><br>
    <label>Языки (Ctrl+множ.выбор): 
        <select name="languages[]" multiple size="6">
            <?php foreach ($allLangs as $lang): ?>
                <option value="<?= $lang ?>" <?= in_array($lang, $selectedLangs) ? 'selected' : '' ?>><?= $lang ?></option>
            <?php endforeach; ?>
        </select>
    </label><br>
    <label>Биография: <textarea name="biography"><?= h($edit['biography']) ?></textarea></label><br>
    <label><input type="checkbox" name="contract" value="1" <?= $edit['contract_accepted'] ? 'checked' : '' ?>> Контракт принят</label><br>
    <button type="submit">Сохранить</button>
</form>
<?php endif; ?>
<p><a href="index.php">Вернуться на главную</a></p>
</body>
</html>
