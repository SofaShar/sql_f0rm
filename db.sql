CREATE TABLE application (
  id int(10) unsigned NOT NULL AUTO_INCREMENT,
  full_name varchar(150) NOT NULL DEFAULT '',
  phone varchar(12) NOT NULL DEFAULT '',
  email varchar(100) NOT NULL DEFAULT '',
  birth_date DATE NOT NULL,
  gender ENUM('male','fmale') NOT NULL,
  bio TEXT,
    contract_accepted TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
)ENGINE=InnoDB;;
-- Таблица заявок

CREATE TABLE language(
  id int(10) unsigned NOT NULL AUTO_INCREMENT,
  name_language varchar(50) NOT NULL DEFAULT '',
  PRIMARY KEY (id)
)ENGINE=InnoDB;;
-- Таблица языков программирования

-- Вставка допустимых языков
INSERT INTO language (name_language) VALUES
('Pascal'), ('C'), ('C++'), ('JavaScript'), ('PHP'), ('Python'),
('Java'), ('Haskell'), ('Clojure'), ('Prolog'), ('Scala'), ('Go');

CREATE TABLE favorite_language(
  id int(10) unsigned NOT NULL AUTO_INCREMENT,
  id_application int(10) unsigned NOT NULL
  id_language int(10) unsigned NOT NULL
  PRIMARY KEY(id),
  FOREIGN KEY(id_application) REFERENCES application(id) ON DELETE CASCADE,
  FOREIGN KEY(id_language) REFERENCES language(id) ON DELETE CASCADE

    FOREIGN KEY (language_id) REFERENCES programming_languages(id) ON DELETE CASCADE
) ENGINE=InnoDB;
-- Таблица связей (один ко многим)
