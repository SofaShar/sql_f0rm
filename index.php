<?php
// Параметры подключения к БД
$host = 'localhost';
$dbname = 'form_db';
$username = 'form_user';
$password = '';

// Функция для подключения к БД с использованием PDO
function getDB() {
    global $host, $dbname, $username, $password;
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        die("Ошибка подключения: " . $e->getMessage());
    }
}

// Список допустимых языков для валидации (получаем из БД)
function getValidLanguages($pdo) {
    $stmt = $pdo->query("SELECT id, name_language FROM language");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = getDB();
    $validLanguages = getValidLanguages($pdo);
    $validLanguageIds = array_column($validLanguages, 'id');

    // Валидация полей
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $birth_date = $_POST['birth_date'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $bio = trim($_POST['bio'] ?? '');
    $contract_accepted = isset($_POST['contract_accepted']) ? 1 : 0;
    $languages = $_POST['languages'] ?? [];

    $errors = [];

    // ФИО: только буквы, пробелы, дефис, длина до 150
    if (!preg_match('/^[a-zA-Zа-яА-ЯёЁ\s\-]{1,150}$/u', $full_name)) {
        $errors[] = 'ФИО должно содержать только буквы, пробелы и дефисы, не более 150 символов.';
    }

    // Телефон: простой формат, можно уточнить (например, цифры, +, -, пробелы)
    if (!preg_match('/^[\+\d\s\-\(\)]{5,20}$/', $phone)) {
        $errors[] = 'Телефон должен быть в формате +7 123 456-78-90 или аналогичном (5-20 символов).';
    }

    // Email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Некорректный email.';
    }

    // Дата рождения: должна быть не в будущем и не слишком старая (например, от 1900)
    $birthDateObj = DateTime::createFromFormat('Y-m-d', $birth_date);
    if (!$birthDateObj || $birthDateObj > new DateTime() || $birthDateObj < new DateTime('1900-01-01')) {
        $errors[] = 'Дата рождения должна быть в формате YYYY-MM-DD и не позднее сегодняшнего дня.';
    }

    // Пол
    if (!in_array($gender, ['male', 'female'])) {
        $errors[] = 'Выберите пол.';
    }

    // Любимые языки: массив из допустимых ID
    if (empty($languages) || !is_array($languages)) {
        $errors[] = 'Выберите хотя бы один язык программирования.';
    } else {
        foreach ($languages as $langId) {
            if (!in_array((int)$langId, $validLanguageIds)) {
                $errors[] = 'Выбран недопустимый язык программирования.';
                break;
            }
        }
    }

    // Биография: не обязательна, но можно проверить длину (например, до 5000)
    if (strlen($bio) > 5000) {
        $errors[] = 'Биография не должна превышать 5000 символов.';
    }

    // Чекбокс согласия
    if (!$contract_accepted) {
        $errors[] = 'Вы должны ознакомиться с контрактом.';
    }

    // Если ошибок нет, сохраняем в БД
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Вставка в таблицу application
            $stmt = $pdo->prepare("INSERT INTO application (full_name, phone, email, birth_date, gender, bio) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$full_name, $phone, $email, $birth_date, $gender, $bio]);
            $appId = $pdo->lastInsertId();

            // Вставка связей в application_language
            $stmtLang = $pdo->prepare("INSERT INTO favorite_language (id_application, id_language) VALUES (?, ?)");
            foreach ($languages as $langId) {
                $stmtLang->execute([$appId, $langId]);
            }

            $pdo->commit();
            $success = 'Данные успешно сохранены!';
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Ошибка сохранения: ' . $e->getMessage();
        }
    }

    // Если есть ошибки, формируем сообщение
    if (!empty($errors)) {
        $error = implode('<br>', $errors);
    }
}
?>