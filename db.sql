-- setup_tables.sql
-- Используем существующую базу данных
USE form_db;

-- Удаляем старые таблицы, если они есть (осторожно! данные будут потеряны)
DROP TABLE IF EXISTS application_languages;
DROP TABLE IF EXISTS applications;
DROP TABLE IF EXISTS programming_languages;
DROP TABLE IF EXISTS admin;

-- Таблица 1: Языки программирования (справочник)
CREATE TABLE programming_languages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Заполняем языками
INSERT INTO programming_languages (name) VALUES
('Pascal'), ('C'), ('C++'), ('JavaScript'), ('PHP'),
('Python'), ('Java'), ('Haskell'), ('Clojure'), 
('Prolog'), ('Scala'), ('Go');

-- Таблица 2: Основная таблица заявок
CREATE TABLE applications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fullname VARCHAR(150) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(100) NOT NULL,
    birthdate DATE NOT NULL,
    gender ENUM('male', 'female', 'other') NOT NULL,
    biography TEXT,
    contract_accepted TINYINT(1) NOT NULL DEFAULT 0,
    login VARCHAR(100) UNIQUE,
    password_hash VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Таблица 3: Связь заявок и языков (один ко многим)
CREATE TABLE application_languages (
    application_id INT UNSIGNED NOT NULL,
    language_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (application_id, language_id),
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
    FOREIGN KEY (language_id) REFERENCES programming_languages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Таблица 4: Администратор
CREATE TABLE admin (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Добавляем администратора (пароль 'secret' в формате MySQL PASSWORD)
INSERT INTO admin (username, password_hash) VALUES ('admin', PASSWORD('secret'));

-- Проверка: показать все таблицы
SHOW TABLES;

-- Проверка: количество языков
SELECT COUNT(*) AS total_languages FROM programming_languages;
