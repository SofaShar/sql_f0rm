<?php
<<<<<<< HEAD
// Настройки подключения к БД
require_once 'config.php';
require_once 'functions.php';

// Инициализация переменных
$errors = [];
$formData = [];
$successMessage = '';
$generatedLogin = '';
$generatedPassword = '';

// Получаем данные для предзаполнения (из Cookies или из сессии авторизованного пользователя)
$isAuthorized = isset($_SESSION['user_id']);
if ($isAuthorized) {
    // Загружаем данные пользователя из БД
    $stmt = $pdo->prepare("SELECT * FROM applications WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userData = $stmt->fetch();
    if ($userData) {
        // Загружаем его языки
        $langStmt = $pdo->prepare("SELECT pl.name FROM application_languages al JOIN programming_languages pl ON al.language_id = pl.id WHERE al.application_id = ?");
        $langStmt->execute([$userData['id']]);
        $userLanguages = $langStmt->fetchAll(PDO::FETCH_COLUMN);
        $formData = [
            'fullname' => $userData['fullname'],
            'phone' => $userData['phone'],
            'email' => $userData['email'],
            'birthdate' => $userData['birthdate'],
            'gender' => $userData['gender'],
            'biography' => $userData['biography'],
            'contract' => $userData['contract_accepted'],
            'languages' => $userLanguages,
        ];
    } else {
        $isAuthorized = false;
        session_destroy();
    }
} else {
    // Неавторизованный: загружаем данные из Cookies (если есть)
    $formData = loadFromCookie();
    // Восстанавливаем ошибки из Cookies (от предыдущей неудачной отправки)
    $errors = loadErrorsAndClear();
}

// Обработка POST (отправка формы)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    // CSRF защита (задание 7)
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('CSRF токен неверен.');
    }

    // Собираем данные
    $input = [
        'fullname' => trim($_POST['fullname'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'birthdate' => $_POST['birthdate'] ?? '',
        'gender' => $_POST['gender'] ?? '',
        'languages' => $_POST['languages'] ?? [],
        'biography' => trim($_POST['biography'] ?? ''),
        'contract' => $_POST['contract'] ?? '',
    ];

    $validationErrors = validateForm($input, $allowedLanguages, $allowedGenders);
    
    if (empty($validationErrors)) {
        try {
            // Если пользователь авторизован – обновляем его запись
            if ($isAuthorized) {
                $updateSql = "UPDATE applications SET fullname=?, phone=?, email=?, birthdate=?, gender=?, biography=?, contract_accepted=? WHERE id=?";
                $stmt = $pdo->prepare($updateSql);
                $stmt->execute([
                    $input['fullname'], $input['phone'], $input['email'], $input['birthdate'],
                    $input['gender'], $input['biography'], $input['contract'], $_SESSION['user_id']
                ]);
                // Обновляем языки: удаляем старые и вставляем новые
                $pdo->prepare("DELETE FROM application_languages WHERE application_id = ?")->execute([$_SESSION['user_id']]);
                $langStmt = $pdo->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (?, (SELECT id FROM programming_languages WHERE name = ?))");
                foreach ($input['languages'] as $lang) {
                    $langStmt->execute([$_SESSION['user_id'], $lang]);
                }
                $successMessage = "Данные успешно обновлены!";
                // Обновляем данные в сессии (не обязательно)
            } else {
                // Новая запись: генерируем логин и пароль
                $login = generateLogin($input['email']);
                $plainPassword = generatePassword();
                $passwordHash = password_hash($plainPassword, PASSWORD_DEFAULT);
                
                $insertSql = "INSERT INTO applications (fullname, phone, email, birthdate, gender, biography, contract_accepted, login, password_hash) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($insertSql);
                $stmt->execute([
                    $input['fullname'], $input['phone'], $input['email'], $input['birthdate'],
                    $input['gender'], $input['biography'], $input['contract'], $login, $passwordHash
                ]);
                $applicationId = $pdo->lastInsertId();
                
                // Вставка языков
                $langStmt = $pdo->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (?, (SELECT id FROM programming_languages WHERE name = ?))");
                foreach ($input['languages'] as $lang) {
                    $langStmt->execute([$applicationId, $lang]);
                }
                
                $successMessage = "Заявка сохранена! Ваш логин: $login, пароль: $plainPassword (сохраните их для редактирования).";
                $generatedLogin = $login;
                $generatedPassword = $plainPassword;
                
                // Сохраняем в Cookies (для неавторизованных) успешные данные на год
                saveToCookie($input);
            }
            // Если был GET с ошибками – они уже удалены. Перенаправляем на ту же страницу, чтобы избежать повторной отправки.
            header("Location: {$_SERVER['PHP_SELF']}?success=1");
            exit;
        } catch (PDOException $e) {
            $errors['general'] = "Ошибка базы данных: " . $e->getMessage();
        }
    } else {
        // Ошибки валидации – сохраняем в Cookies и перенаправляем GET (задание 4)
        saveErrorsToCookie($validationErrors);
        // Сохраняем введённые данные (кроме пароля) в сессию или в GET параметры? Но задание требует GET.
        // Проще: закодировать данные в GET, но это громоздко. Используем временную сессию.
        $_SESSION['old_input'] = $input;
        header("Location: {$_SERVER['PHP_SELF']}");
        exit;
    }
}

// Генерация CSRF токена
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Если GET с success – просто показываем сообщение из сессии (сохраним его в сессии)
if (isset($_GET['success']) && !empty($successMessage)) {
    // Ничего дополнительно не делаем, $successMessage уже установлена
}

// Восстановление старых данных из сессии после ошибки (для GET)
if (isset($_SESSION['old_input']) && !$isAuthorized) {
    $formData = array_merge($formData, $_SESSION['old_input']);
    unset($_SESSION['old_input']);
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Анкета разработчика</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f0f2f5; margin: 0; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 25px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1 { text-align: center; color: #2c3e50; }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; }
        input[type="text"], input[type="tel"], input[type="email"], input[type="date"], textarea, select {
            width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;
        }
        .radio-group label { display: inline-block; margin-right: 15px; font-weight: normal; }
        .error { color: red; font-size: 0.9em; margin-top: 5px; }
        .error-border { border: 1px solid red !important; }
        .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin-bottom: 20px; }
        button { background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-size: 16px; }
        button:hover { background: #218838; }
        .auth-info { text-align: right; margin-bottom: 20px; }
        .auth-info a { margin-left: 10px; }
    </style>
</head>
<body>
<div class="container">
    <div class="auth-info">
        <?php if ($isAuthorized): ?>
            Вы вошли как <?= h($formData['fullname'] ?? 'пользователь') ?> 
            <a href="logout.php">Выйти</a>
        <?php else: ?>
            <a href="login.php">Вход для редактирования</a>
        <?php endif; ?>
    </div>
    <h1>Анкета разработчика</h1>
    <?php if ($successMessage): ?>
        <div class="success"><?= nl2br(h($successMessage)) ?></div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="error" style="background:#f8d7da; padding:10px; margin-bottom:15px;">
            <strong>Исправьте ошибки:</strong><br>
            <?php foreach ($errors as $err): ?>
                - <?= h($err) ?><br>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <form method="post" action="">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        
        <div class="form-group">
            <label>ФИО:</label>
            <input type="text" name="fullname" value="<?= h($formData['fullname'] ?? '') ?>" class="<?= isset($errors['fullname']) ? 'error-border' : '' ?>">
            <?php if (isset($errors['fullname'])) echo '<div class="error">'.$errors['fullname'].'</div>'; ?>
        </div>
        
        <div class="form-group">
            <label>Телефон:</label>
            <input type="tel" name="phone" value="<?= h($formData['phone'] ?? '') ?>" class="<?= isset($errors['phone']) ? 'error-border' : '' ?>">
            <?php if (isset($errors['phone'])) echo '<div class="error">'.$errors['phone'].'</div>'; ?>
        </div>
        
        <div class="form-group">
            <label>Email:</label>
            <input type="email" name="email" value="<?= h($formData['email'] ?? '') ?>" class="<?= isset($errors['email']) ? 'error-border' : '' ?>">
            <?php if (isset($errors['email'])) echo '<div class="error">'.$errors['email'].'</div>'; ?>
        </div>
        
        <div class="form-group">
            <label>Дата рождения:</label>
            <input type="date" name="birthdate" value="<?= h($formData['birthdate'] ?? '') ?>" class="<?= isset($errors['birthdate']) ? 'error-border' : '' ?>">
            <?php if (isset($errors['birthdate'])) echo '<div class="error">'.$errors['birthdate'].'</div>'; ?>
        </div>
        
        <div class="form-group">
            <label>Пол:</label>
            <div class="radio-group">
                <label><input type="radio" name="gender" value="male" <?= (($formData['gender'] ?? '') == 'male') ? 'checked' : '' ?>> Мужской</label>
                <label><input type="radio" name="gender" value="female" <?= (($formData['gender'] ?? '') == 'female') ? 'checked' : '' ?>> Женский</label>
                <label><input type="radio" name="gender" value="other" <?= (($formData['gender'] ?? '') == 'other') ? 'checked' : '' ?>> Другой</label>
            </div>
            <?php if (isset($errors['gender'])) echo '<div class="error">'.$errors['gender'].'</div>'; ?>
        </div>
        
        <div class="form-group">
            <label>Любимые языки программирования (множественный выбор):</label>
            <select name="languages[]" multiple size="6">
                <?php foreach ($allowedLanguages as $lang): ?>
                    <option value="<?= $lang ?>" <?= (isset($formData['languages']) && in_array($lang, $formData['languages'])) ? 'selected' : '' ?>><?= $lang ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['languages'])) echo '<div class="error">'.$errors['languages'].'</div>'; ?>
        </div>
        
        <div class="form-group">
            <label>Биография:</label>
            <textarea name="biography" rows="5" class="<?= isset($errors['biography']) ? 'error-border' : '' ?>"><?= h($formData['biography'] ?? '') ?></textarea>
            <?php if (isset($errors['biography'])) echo '<div class="error">'.$errors['biography'].'</div>'; ?>
        </div>
        
        <div class="form-group">
            <label><input type="checkbox" name="contract" value="1" <?= (!empty($formData['contract'])) ? 'checked' : '' ?>> Я ознакомлен(а) с контрактом</label>
            <?php if (isset($errors['contract'])) echo '<div class="error">'.$errors['contract'].'</div>'; ?>
        </div>
        
        <button type="submit" name="submit">Сохранить</button>
    </form>
</div>
</body>
</html>
