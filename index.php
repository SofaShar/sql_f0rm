<?php
session_start();

// ------ ПОДКЛЮЧЕНИЕ К БД ------
$host = 'localhost';
$dbname = 'form_db';
$username = 'user1';
$password_db = '123'; // замените на ваш пароль

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password_db);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Ошибка БД: " . $e->getMessage());
}

// ------ ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ ------
function getLanguages($pdo) {
    $stmt = $pdo->query("SELECT id, name FROM programming_languages ORDER BY name");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function validateFormData($data, &$errors, $languages) {
    // ФИО
    if (empty($data['full_name'])) {
        $errors['full_name'] = 'ФИО обязательно.';
    } elseif (!preg_match('/^[a-zA-Zа-яА-ЯёЁ\s\-]+$/u', $data['full_name'])) {
        $errors['full_name'] = 'ФИО: буквы, пробелы, дефис.';
    } elseif (mb_strlen($data['full_name']) > 150) {
        $errors['full_name'] = 'ФИО не длиннее 150 символов.';
    }

    // Телефон
    if (empty($data['phone'])) {
        $errors['phone'] = 'Телефон обязателен.';
    } elseif (!preg_match('/^[+\d\s\-]{5,20}$/', $data['phone'])) {
        $errors['phone'] = 'Телефон: цифры, +, -, пробелы, 5-20 символов.';
    }

    // Email
    if (empty($data['email'])) {
        $errors['email'] = 'Email обязателен.';
    } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Некорректный email.';
    }

    // Дата рождения
    if (empty($data['birth_date'])) {
        $errors['birth_date'] = 'Дата рождения обязательна.';
    } else {
        $date = DateTime::createFromFormat('Y-m-d', $data['birth_date']);
        if (!$date || $date->format('Y-m-d') !== $data['birth_date'] || $date > new DateTime()) {
            $errors['birth_date'] = 'Некорректная или будущая дата.';
        }
    }

    // Пол
    if (!in_array($data['gender'], ['male', 'female'])) {
        $errors['gender'] = 'Выберите пол.';
    }

    // Биография
    if (mb_strlen($data['biography']) > 1000) {
        $errors['biography'] = 'Биография до 1000 символов.';
    }

    // Контракт
    if (empty($data['contract_agreed'])) {
        $errors['contract_agreed'] = 'Подтвердите ознакомление с контрактом.';
    }

    // Языки
    $validLangIds = array_column($languages, 'id');
    if (empty($data['languages'])) {
        $errors['languages'] = 'Выберите хотя бы один язык.';
    } else {
        foreach ($data['languages'] as $langId) {
            if (!in_array((int)$langId, $validLangIds)) {
                $errors['languages'] = 'Недопустимый язык.';
                break;
            }
        }
    }
}

function generateLogin() {
    return 'user_' . bin2hex(random_bytes(4)); // например user_a1b2c3d4
}

function generatePassword() {
    return bin2hex(random_bytes(6)); // 12 символов
}

// ------ ОБРАБОТКА ВХОДА/ВЫХОДА ------
$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_action'])) {
    if ($_POST['login_action'] === 'login') {
        $login = trim($_POST['login'] ?? '');
        $password = $_POST['password'] ?? '';
        $stmt = $pdo->prepare("SELECT id, password_hash FROM applications WHERE login = ?");
        $stmt->execute([$login]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            // Перенаправляем, чтобы очистить POST
            header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?'));
            exit;
        } else {
            $login_error = 'Неверный логин или пароль.';
        }
    } elseif ($_POST['login_action'] === 'logout') {
        session_destroy();
        header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?'));
        exit;
    }
}

// ------ ЗАГРУЗКА ДАННЫХ ДЛЯ ФОРМЫ (если авторизован) ------
$editMode = isset($_SESSION['user_id']);
$formData = []; // для вывода в форму
$languagesList = getLanguages($pdo);

if ($editMode) {
    $stmt = $pdo->prepare("SELECT * FROM applications WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($userData) {
        $formData = [
            'full_name' => $userData['full_name'],
            'phone' => $userData['phone'],
            'email' => $userData['email'],
            'birth_date' => $userData['birth_date'],
            'gender' => $userData['gender'],
            'biography' => $userData['biography'],
            'contract_agreed' => $userData['contract_agreed'],
        ];
        // загружаем выбранные языки
        $stmtLang = $pdo->prepare("SELECT language_id FROM application_languages WHERE application_id = ?");
        $stmtLang->execute([$_SESSION['user_id']]);
        $formData['languages'] = $stmtLang->fetchAll(PDO::FETCH_COLUMN);
    }
}

// ------ ОБРАБОТКА ОТПРАВКИ ФОРМЫ (СОХРАНЕНИЕ ИЛИ ОБНОВЛЕНИЕ) ------
$successMessage = '';
$generatedCredentials = [];
$errors = [];
$oldInput = [];

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['submit'])) {
    // Собираем данные из GET
    $input = [
        'full_name' => trim($_GET['full_name'] ?? ''),
        'phone' => trim($_GET['phone'] ?? ''),
        'email' => trim($_GET['email'] ?? ''),
        'birth_date' => trim($_GET['birth_date'] ?? ''),
        'gender' => $_GET['gender'] ?? '',
        'biography' => trim($_GET['biography'] ?? ''),
        'contract_agreed' => isset($_GET['contract_agreed']),
        'languages' => $_GET['languages'] ?? []
    ];

    validateFormData($input, $errors, $languagesList);

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            if ($editMode && isset($_SESSION['user_id'])) {
                // ----- ОБНОВЛЕНИЕ существующей записи -----
                $stmt = $pdo->prepare("
                    UPDATE applications SET
                        full_name = :full_name,
                        phone = :phone,
                        email = :email,
                        birth_date = :birth_date,
                        gender = :gender,
                        biography = :biography,
                        contract_agreed = :contract_agreed
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':full_name' => $input['full_name'],
                    ':phone' => $input['phone'],
                    ':email' => $input['email'],
                    ':birth_date' => $input['birth_date'],
                    ':gender' => $input['gender'],
                    ':biography' => $input['biography'],
                    ':contract_agreed' => $input['contract_agreed'] ? 1 : 0,
                    ':id' => $_SESSION['user_id']
                ]);
                $applicationId = $_SESSION['user_id'];
                // Обновляем языки: удаляем старые, вставляем новые
                $pdo->prepare("DELETE FROM application_languages WHERE application_id = ?")->execute([$applicationId]);
                $stmtLang = $pdo->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (?, ?)");
                foreach ($input['languages'] as $langId) {
                    $stmtLang->execute([$applicationId, $langId]);
                }
                $successMessage = 'Данные успешно обновлены!';
            } else {
                // ----- НОВАЯ ЗАПИСЬ: генерируем логин и пароль -----
                $login = generateLogin();
                $plainPassword = generatePassword();
                $passwordHash = password_hash($plainPassword, PASSWORD_DEFAULT);

                $stmt = $pdo->prepare("
                    INSERT INTO applications (full_name, phone, email, login, password_hash, birth_date, gender, biography, contract_agreed)
                    VALUES (:full_name, :phone, :email, :login, :password_hash, :birth_date, :gender, :biography, :contract_agreed)
                ");
                $stmt->execute([
                    ':full_name' => $input['full_name'],
                    ':phone' => $input['phone'],
                    ':email' => $input['email'],
                    ':login' => $login,
                    ':password_hash' => $passwordHash,
                    ':birth_date' => $input['birth_date'],
                    ':gender' => $input['gender'],
                    ':biography' => $input['biography'],
                    ':contract_agreed' => $input['contract_agreed'] ? 1 : 0
                ]);
                $applicationId = $pdo->lastInsertId();

                $stmtLang = $pdo->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (?, ?)");
                foreach ($input['languages'] as $langId) {
                    $stmtLang->execute([$applicationId, $langId]);
                }

                // Сохраняем сгенерированные данные для отображения
                $generatedCredentials = ['login' => $login, 'password' => $plainPassword];
                $successMessage = 'Данные успешно сохранены!';
            }

            $pdo->commit();

            // Если пользователь был не авторизован, после создания новой записи можно сразу залогинить его?
            // По заданию: пароль отображается один раз, вход отдельно. Не делаем авто-логин.
            // Но сохраняем в Cookies успешные данные на год (как в задании 4) для неавторизованных
            if (!$editMode) {
                setcookie('saved_full_name', $input['full_name'], time() + 365*24*3600, '/');
                setcookie('saved_phone', $input['phone'], time() + 365*24*3600, '/');
                setcookie('saved_email', $input['email'], time() + 365*24*3600, '/');
                setcookie('saved_birth_date', $input['birth_date'], time() + 365*24*3600, '/');
                setcookie('saved_gender', $input['gender'], time() + 365*24*3600, '/');
                setcookie('saved_biography', $input['biography'], time() + 365*24*3600, '/');
                setcookie('saved_languages', implode(',', $input['languages']), time() + 365*24*3600, '/');
                setcookie('saved_contract', $input['contract_agreed'] ? '1' : '0', time() + 365*24*3600, '/');
            }

            // Очищаем Cookies ошибок (были при неудачной отправке)
            setcookie('form_errors', '', time() - 3600, '/');
            setcookie('old_input', '', time() - 3600, '/');

            // Перенаправляем, чтобы избавиться от GET-параметров
            header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?'));
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors['db'] = 'Ошибка сохранения: ' . $e->getMessage();
            // Сохраняем ошибки и ввод в Cookies
            setcookie('form_errors', serialize($errors), time() + 3600, '/');
            setcookie('old_input', serialize($input), time() + 3600, '/');
            header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?'));
            exit;
        }
    } else {
        // Ошибки валидации
        setcookie('form_errors', serialize($errors), time() + 3600, '/');
        setcookie('old_input', serialize($input), time() + 3600, '/');
        header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?'));
        exit;
    }
}

// ------ ЧТЕНИЕ COOKIES ДЛЯ ОШИБОК И СТАРЫХ ВВОДОВ (НЕАВТОРИЗОВАННЫЕ ПОЛЬЗОВАТЕЛИ) ------
$errors = [];
$oldInput = [];
if (!$editMode) {
    if (isset($_COOKIE['form_errors'])) {
        $errors = unserialize($_COOKIE['form_errors']);
        setcookie('form_errors', '', time() - 3600, '/');
    }
    if (isset($_COOKIE['old_input'])) {
        $oldInput = unserialize($_COOKIE['old_input']);
        setcookie('old_input', '', time() - 3600, '/');
    }
}

// ------ СОХРАНЕННЫЕ COOKIES ДЛЯ ПОДСТАНОВКИ (НЕАВТОРИЗОВАННЫЕ) ------
$savedCookies = [];
if (!$editMode) {
    $savedCookies = [
        'full_name' => $_COOKIE['saved_full_name'] ?? '',
        'phone' => $_COOKIE['saved_phone'] ?? '',
        'email' => $_COOKIE['saved_email'] ?? '',
        'birth_date' => $_COOKIE['saved_birth_date'] ?? '',
        'gender' => $_COOKIE['saved_gender'] ?? '',
        'biography' => $_COOKIE['saved_biography'] ?? '',
        'languages' => isset($_COOKIE['saved_languages']) ? explode(',', $_COOKIE['saved_languages']) : [],
        'contract_agreed' => ($_COOKIE['saved_contract'] ?? '') === '1'
    ];
}

// Функция для получения значения поля (приоритет: старые ошибки > editMode данные > savedCookies)
function getFieldValue($fieldName, $oldInput, $editModeData, $savedCookies) {
    if (!empty($oldInput[$fieldName])) {
        return htmlspecialchars($oldInput[$fieldName]);
    }
    if (!empty($editModeData[$fieldName])) {
        return htmlspecialchars($editModeData[$fieldName]);
    }
    return htmlspecialchars($savedCookies[$fieldName] ?? '');
}

function isFieldError($fieldName, $errors) {
    return isset($errors[$fieldName]);
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Анкета разработчика (с авторизацией)</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <h1>Анкета разработчика</h1>

    <?php if ($successMessage): ?>
        <div class="success"><?= htmlspecialchars($successMessage) ?></div>
    <?php endif; ?>

    <?php if (!empty($generatedCredentials)): ?>
        <div class="credentials">
            <strong>Ваши данные для входа (сохраните их!):</strong><br>
            Логин: <?= htmlspecialchars($generatedCredentials['login']) ?><br>
            Пароль: <?= htmlspecialchars($generatedCredentials['password']) ?>
        </div>
    <?php endif; ?>

    <!-- Блок входа / выхода -->
    <div class="auth-block">
        <?php if ($editMode): ?>
            <p>Вы вошли как пользователь ID <?= $_SESSION['user_id'] ?></p>
            <form method="post" style="display:inline;">
                <input type="hidden" name="login_action" value="logout">
                <button type="submit" class="logout-btn">Выйти</button>
            </form>
        <?php else: ?>
            <form method="post">
                <h3>Вход для редактирования</h3>
                <input type="text" name="login" placeholder="Логин" required>
                <input type="password" name="password" placeholder="Пароль" required>
                <input type="hidden" name="login_action" value="login">
                <button type="submit">Войти</button>
                <?php if ($login_error): ?>
                    <div class="error-msg"><?= htmlspecialchars($login_error) ?></div>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </div>

    <!-- Вывод общих ошибок -->
    <?php if (!empty($errors)): ?>
        <div class="errors">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Форма -->
    <form method="get" action="">
        <div class="form-group <?= isFieldError('full_name', $errors) ? 'has-error' : '' ?>">
            <label>ФИО *</label>
            <input type="text" name="full_name" value="<?= getFieldValue('full_name', $oldInput, $formData, $savedCookies) ?>">
            <?php if (isFieldError('full_name', $errors)): ?>
                <span class="error-msg"><?= htmlspecialchars($errors['full_name']) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-group <?= isFieldError('phone', $errors) ? 'has-error' : '' ?>">
            <label>Телефон *</label>
            <input type="tel" name="phone" value="<?= getFieldValue('phone', $oldInput, $formData, $savedCookies) ?>">
            <?php if (isFieldError('phone', $errors)): ?>
                <span class="error-msg"><?= htmlspecialchars($errors['phone']) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-group <?= isFieldError('email', $errors) ? 'has-error' : '' ?>">
            <label>E-mail *</label>
            <input type="email" name="email" value="<?= getFieldValue('email', $oldInput, $formData, $savedCookies) ?>">
            <?php if (isFieldError('email', $errors)): ?>
                <span class="error-msg"><?= htmlspecialchars($errors['email']) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-group <?= isFieldError('birth_date', $errors) ? 'has-error' : '' ?>">
            <label>Дата рождения *</label>
            <input type="date" name="birth_date" value="<?= getFieldValue('birth_date', $oldInput, $formData, $savedCookies) ?>">
            <?php if (isFieldError('birth_date', $errors)): ?>
                <span class="error-msg"><?= htmlspecialchars($errors['birth_date']) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-group <?= isFieldError('gender', $errors) ? 'has-error' : '' ?>">
            <label>Пол *</label>
            <div class="radio-group">
                <label><input type="radio" name="gender" value="male" <?= (getFieldValue('gender', $oldInput, $formData, $savedCookies) === 'male') ? 'checked' : '' ?>> Мужской</label>
                <label><input type="radio" name="gender" value="female" <?= (getFieldValue('gender', $oldInput, $formData, $savedCookies) === 'female') ? 'checked' : '' ?>> Женский</label>
            </div>
            <?php if (isFieldError('gender', $errors)): ?>
                <span class="error-msg"><?= htmlspecialchars($errors['gender']) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-group <?= isFieldError('languages', $errors) ? 'has-error' : '' ?>">
            <label>Любимый язык * (можно несколько)</label>
            <select name="languages[]" multiple size="6">
                <?php
                $selectedLanguages = [];
                if (!empty($oldInput['languages'])) {
                    $selectedLanguages = $oldInput['languages'];
                } elseif ($editMode && !empty($formData['languages'])) {
                    $selectedLanguages = $formData['languages'];
                } elseif (!$editMode && !empty($savedCookies['languages'])) {
                    $selectedLanguages = $savedCookies['languages'];
                }
                foreach ($languagesList as $lang): ?>
                    <option value="<?= $lang['id'] ?>" <?= in_array($lang['id'], $selectedLanguages) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($lang['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (isFieldError('languages', $errors)): ?>
                <span class="error-msg"><?= htmlspecialchars($errors['languages']) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-group <?= isFieldError('biography', $errors) ? 'has-error' : '' ?>">
            <label>Биография</label>
            <textarea name="biography" rows="4"><?= getFieldValue('biography', $oldInput, $formData, $savedCookies) ?></textarea>
            <?php if (isFieldError('biography', $errors)): ?>
                <span class="error-msg"><?= htmlspecialchars($errors['biography']) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-group checkbox <?= isFieldError('contract_agreed', $errors) ? 'has-error' : '' ?>">
            <label>
                <input type="checkbox" name="contract_agreed" value="1" <?= (getFieldValue('contract_agreed', $oldInput, $formData, $savedCookies) == 1) ? 'checked' : '' ?>>
                С контрактом ознакомлен(а) *
            </label>
            <?php if (isFieldError('contract_agreed', $errors)): ?>
                <span class="error-msg"><?= htmlspecialchars($errors['contract_agreed']) ?></span>
            <?php endif; ?>
        </div>

        <button type="submit" name="submit">Сохранить</button>
    </form>
</div>
</body>
</html>
