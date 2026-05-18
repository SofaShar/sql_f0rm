<?php
// functions.php
require_once 'config.php';

// Валидация полей (возвращает массив ошибок)
function validateForm($data, $allowedLanguages, $allowedGenders) {
    $errors = [];

    // ФИО: только буквы, пробелы, дефис, длина 1-150
    if (empty($data['fullname']) || !preg_match('/^[a-zA-Zа-яА-ЯёЁ\s\-]{1,150}$/u', $data['fullname'])) {
        $errors['fullname'] = 'ФИО должно содержать только буквы, пробелы и дефис (до 150 символов).';
    }

    // Телефон: цифры, +, -, пробелы, скобки (простейшая проверка)
    if (empty($data['phone']) || !preg_match('/^[+\d\s\-()]{5,20}$/', $data['phone'])) {
        $errors['phone'] = 'Введите корректный номер телефона (5-20 символов).';
    }

    // Email
    if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Введите корректный email.';
    }

    // Дата рождения: формат YYYY-MM-DD, не в будущем
    if (empty($data['birthdate']) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['birthdate']) || $data['birthdate'] > date('Y-m-d')) {
        $errors['birthdate'] = 'Введите корректную дату рождения (не в будущем).';
    }

    // Пол
    if (empty($data['gender']) || !in_array($data['gender'], $allowedGenders)) {
        $errors['gender'] = 'Выберите корректный пол.';
    }

    // Любимые языки: массив, не пустой, каждый язык есть в списке
    if (empty($data['languages']) || !is_array($data['languages'])) {
        $errors['languages'] = 'Выберите хотя бы один язык программирования.';
    } else {
        foreach ($data['languages'] as $lang) {
            if (!in_array($lang, $allowedLanguages)) {
                $errors['languages'] = 'Выбран недопустимый язык.';
                break;
            }
        }
    }

    // Биография: не более 1000 символов (можно любые символы)
    if (strlen($data['biography'] ?? '') > 1000) {
        $errors['biography'] = 'Биография не должна превышать 1000 символов.';
    }

    // Чекбокс контракта
    if (empty($data['contract']) || $data['contract'] != '1') {
        $errors['contract'] = 'Вы должны согласиться с контрактом.';
    }

    return $errors;
}

// Сохранить отправленные данные в Cookies на год (успешные, без пароля)
function saveToCookie($data) {
    $cookieData = [
        'fullname' => $data['fullname'] ?? '',
        'phone' => $data['phone'] ?? '',
        'email' => $data['email'] ?? '',
        'birthdate' => $data['birthdate'] ?? '',
        'gender' => $data['gender'] ?? '',
        'biography' => $data['biography'] ?? '',
    ];
    setcookie('saved_form', json_encode($cookieData), time()+31536000, '/');
}

// Загрузить сохранённые Cookies в массив (для предзаполнения формы)
function loadFromCookie() {
    if (isset($_COOKIE['saved_form'])) {
        return json_decode($_COOKIE['saved_form'], true);
    }
    return [];
}

// Сохранить ошибки в Cookies (временные)
function saveErrorsToCookie($errors) {
    setcookie('form_errors', json_encode($errors), 0, '/'); // до конца сессии браузера
}

// Загрузить ошибки и удалить cookie
function loadErrorsAndClear() {
    $errors = [];
    if (isset($_COOKIE['form_errors'])) {
        $errors = json_decode($_COOKIE['form_errors'], true);
        setcookie('form_errors', '', time()-3600, '/'); // удаляем
    }
    return $errors;
}

// Генерация логина (на основе email или случайный)
function generateLogin($email) {
    $base = explode('@', $email)[0];
    $base = preg_replace('/[^a-zA-Z0-9]/', '', $base);
    if (strlen($base) < 3) $base = 'user';
    $suffix = rand(100, 999);
    return $base . $suffix;
}

// Генерация случайного пароля (8 символов)
function generatePassword() {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    return substr(str_shuffle($chars), 0, 8);
}
?>
