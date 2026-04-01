<?php
// Настройки подключения к БД
$host = 'localhost';
$dbname = 'form_db';
$username = 'app_user';
$password = 'strong_password';

$errors = [];
$success = false;
$formData = [];

// Подключение к БД с PDO
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Ошибка подключения к базе данных: " . $e->getMessage());
}

// Получаем список языков для выпадающего списка
$languages = [];
$stmt = $pdo->query("SELECT id, name FROM programming_languages ORDER BY name");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $languages[] = $row;
}

// Обработка отправки формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Получаем и очищаем данные
    $formData = [
        'full_name' => trim($_POST['full_name'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'birth_date' => trim($_POST['birth_date'] ?? ''),
        'gender' => $_POST['gender'] ?? '',
        'biography' => trim($_POST['biography'] ?? ''),
        'contract_agreed' => isset($_POST['contract_agreed']),
        'languages' => $_POST['languages'] ?? []
    ];

    // Валидация

    // ФИО: только буквы и пробелы, длина ≤ 150
    if (empty($formData['full_name'])) {
        $errors['full_name'] = 'Поле "ФИО" обязательно.';
    } elseif (!preg_match('/^[a-zA-Zа-яА-ЯёЁ\s]+$/u', $formData['full_name'])) {
        $errors['full_name'] = 'ФИО должно содержать только буквы и пробелы.';
    } elseif (mb_strlen($formData['full_name']) > 150) {
        $errors['full_name'] = 'ФИО не должно превышать 150 символов.';
    }

    // Телефон (простая проверка: цифры, +, -, пробелы, длина 5-20)
    if (empty($formData['phone'])) {
        $errors['phone'] = 'Поле "Телефон" обязательно.';
    } elseif (!preg_match('/^[+\d\s\-]{5,20}$/', $formData['phone'])) {
        $errors['phone'] = 'Некорректный формат телефона.';
    }

    // Email
    if (empty($formData['email'])) {
        $errors['email'] = 'Поле "Email" обязательно.';
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Некорректный email адрес.';
    }

    // Дата рождения: должна быть валидной и не будущей
    if (empty($formData['birth_date'])) {
        $errors['birth_date'] = 'Поле "Дата рождения" обязательно.';
    } else {
        $date = DateTime::createFromFormat('Y-m-d', $formData['birth_date']);
        if (!$date || $date->format('Y-m-d') !== $formData['birth_date']) {
            $errors['birth_date'] = 'Некорректная дата (формат ГГГГ-ММ-ДД).';
        } elseif ($date > new DateTime()) {
            $errors['birth_date'] = 'Дата рождения не может быть в будущем.';
        }
    }

    // Пол: допустимые значения
    if (!in_array($formData['gender'], ['male', 'female'])) {
        $errors['gender'] = 'Выберите пол.';
    }

    // Биография: необязательно, но можно проверить длину (макс 1000)
    if (mb_strlen($formData['biography']) > 1000) {
        $errors['biography'] = 'Биография не должна превышать 1000 символов.';
    }

    // Чекбокс контракта
    if (!$formData['contract_agreed']) {
        $errors['contract_agreed'] = 'Необходимо подтвердить ознакомление с контрактом.';
    }

    // Языки: минимум один, все должны существовать в справочнике
    if (empty($formData['languages'])) {
        $errors['languages'] = 'Выберите хотя бы один язык программирования.';
    } else {
        $validLangIds = array_column($languages, 'id');
        foreach ($formData['languages'] as $langId) {
            if (!in_array((int)$langId, $validLangIds)) {
                $errors['languages'] = 'Выбран недопустимый язык.';
                break;
            }
        }
    }

    // Если ошибок нет, сохраняем в БД
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Вставка заявки
            $stmt = $pdo->prepare("
                INSERT INTO applications (full_name, phone, email, birth_date, gender, biography, contract_agreed)
                VALUES (:full_name, :phone, :email, :birth_date, :gender, :biography, :contract_agreed)
            ");
            $stmt->execute([
                ':full_name' => $formData['full_name'],
                ':phone' => $formData['phone'],
                ':email' => $formData['email'],
                ':birth_date' => $formData['birth_date'],
                ':gender' => $formData['gender'],
                ':biography' => $formData['biography'],
                ':contract_agreed' => $formData['contract_agreed'] ? 1 : 0
            ]);
            $applicationId = $pdo->lastInsertId();

            // Вставка выбранных языков в связующую таблицу
            $stmtLang = $pdo->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (:app_id, :lang_id)");
            foreach ($formData['languages'] as $langId) {
                $stmtLang->execute([':app_id' => $applicationId, ':lang_id' => $langId]);
            }

            $pdo->commit();
            $success = true;
            $formData = []; // очищаем форму после успеха
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors['db'] = 'Ошибка при сохранении данных: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Анкета разработчика</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>Анкета разработчика</h1>

        <?php if ($success): ?>
            <div class="success">Данные успешно сохранены!</div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="errors">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" novalidate>
            <div class="form-group">
                <label for="full_name">ФИО *</label>
                <input type="text" id="full_name" name="full_name" value="<?= htmlspecialchars($formData['full_name'] ?? '') ?>" required>
                <?php if (isset($errors['full_name'])): ?>
                    <span class="error"><?= htmlspecialchars($errors['full_name']) ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="phone">Телефон *</label>
                <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($formData['phone'] ?? '') ?>" required>
                <?php if (isset($errors['phone'])): ?>
                    <span class="error"><?= htmlspecialchars($errors['phone']) ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="email">E-mail *</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($formData['email'] ?? '') ?>" required>
                <?php if (isset($errors['email'])): ?>
                    <span class="error"><?= htmlspecialchars($errors['email']) ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="birth_date">Дата рождения *</label>
                <input type="date" id="birth_date" name="birth_date" value="<?= htmlspecialchars($formData['birth_date'] ?? '') ?>" required>
                <?php if (isset($errors['birth_date'])): ?>
                    <span class="error"><?= htmlspecialchars($errors['birth_date']) ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label>Пол *</label>
                <div class="radio-group">
                    <label><input type="radio" name="gender" value="male" <?= (isset($formData['gender']) && $formData['gender'] === 'male') ? 'checked' : '' ?>> Мужской</label>
                    <label><input type="radio" name="gender" value="female" <?= (isset($formData['gender']) && $formData['gender'] === 'female') ? 'checked' : '' ?>> Женский</label>
                </div>
                <?php if (isset($errors['gender'])): ?>
                    <span class="error"><?= htmlspecialchars($errors['gender']) ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="languages">Любимый язык программирования * (можно выбрать несколько)</label>
                <select id="languages" name="languages[]" multiple size="6">
                    <?php foreach ($languages as $lang): ?>
                        <option value="<?= $lang['id'] ?>" <?= (isset($formData['languages']) && in_array($lang['id'], $formData['languages'])) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($lang['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['languages'])): ?>
                    <span class="error"><?= htmlspecialchars($errors['languages']) ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="biography">Биография</label>
                <textarea id="biography" name="biography" rows="5"><?= htmlspecialchars($formData['biography'] ?? '') ?></textarea>
                <?php if (isset($errors['biography'])): ?>
                    <span class="error"><?= htmlspecialchars($errors['biography']) ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group checkbox">
                <label>
                    <input type="checkbox" name="contract_agreed" <?= (isset($formData['contract_agreed']) && $formData['contract_agreed']) ? 'checked' : '' ?>>
                    С контрактом ознакомлен(а) *
                </label>
                <?php if (isset($errors['contract_agreed'])): ?>
                    <span class="error"><?= htmlspecialchars($errors['contract_agreed']) ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <button type="submit">Сохранить</button>
            </div>
        </form>
    </div>
</body>
</html>