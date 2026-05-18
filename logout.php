<?php
// logout.php
session_start();
session_destroy();          // удаляет все данные сессии
header("Location: index.php");
exit;
