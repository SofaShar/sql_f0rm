<?php
// config.php - настроенный под вашу БД
$host = 'localhost';
$dbname = 'form_db';        // ваша существующая база данных
$username = 'user1';         // ваш существующий пользователь
$password = '123';    // замените на реальный пароль user1

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    echo "Подключение к БД успешно!"; // временно для проверки
} catch (PDOException $e) {
    die("Ошибка подключения к БД: " . $e->getMessage());
}

session_start();

function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// Список допустимых языков
$allowedLanguages = ['Pascal', 'C', 'C++', 'JavaScript', 'PHP', 'Python', 'Java', 'Haskell', 'Clojure', 'Prolog', 'Scala', 'Go'];

// Допустимые значения пола
$allowedGenders = ['male', 'female', 'other'];
?>
