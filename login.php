<?php
require_once 'config.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = $_POST['login'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $stmt = $pdo->prepare("SELECT id, password_hash FROM applications WHERE login = ?");
    $stmt->execute([$login]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        header("Location: index.php");
        exit;
    } else {
        $error = "Неверный логин или пароль";
    }
}
?>
<!DOCTYPE html>
<html>
<head><title>Вход</title><link rel="stylesheet" href="style.css"></head>
<body>
<div class="container">
    <h1>Вход для редактирования анкеты</h1>
    <?php if (isset($error)) echo "<div class='error'>$error</div>"; ?>
    <form method="post">
        <div class="form-group"><label>Логин:</label><input type="text" name="login" required></div>
        <div class="form-group"><label>Пароль:</label><input type="password" name="password" required></div>
        <button type="submit">Войти</button>
    </form>
    <p><a href="index.php">На главную</a></p>
</div>
</body>
</html>
