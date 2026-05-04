<?php
// config.php
$host = 'localhost';
$dbname = 'app_db';
$username = 'app_user';
$password = 'strong_password';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Ошибка подключения к БД: " . $e->getMessage());
}

// Запуск сессии (для задания 5)
session_start();

// Функция для безопасного вывода в HTML
function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// Список допустимых языков (можно загрузить из БД, но для валидации удобно)
$allowedLanguages = ['Pascal', 'C', 'C++', 'JavaScript', 'PHP', 'Python', 'Java', 'Haskell', 'Clojure', 'Prolog', 'Scala', 'Go'];

// Допустимые значения пола
$allowedGenders = ['male', 'female', 'other'];
?>
