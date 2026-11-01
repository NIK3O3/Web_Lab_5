SET NAMES utf8mb4;
SET time_zone = '+00:00';
CREATE TABLE cargo_types (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE,
    rate_cents INT NOT NULL CHECK (rate_cents BETWEEN 1 AND 100000),
    minimum_cents INT NOT NULL CHECK (minimum_cents BETWEEN 0 AND 10000000),
    description VARCHAR(200) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE routes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    origin VARCHAR(80) NOT NULL,
    destination VARCHAR(80) NOT NULL,
    via VARCHAR(120) NOT NULL,
    distance10 INT NOT NULL CHECK (distance10 BETWEEN 1 AND 100000),
    factor_pct INT NOT NULL CHECK (factor_pct BETWEEN 50 AND 300),
    fee_cents INT NOT NULL CHECK (fee_cents BETWEEN 0 AND 10000000),
    UNIQUE KEY uq_route (origin,destination,via),
    CHECK (origin <> destination)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE quotes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cargo_id INT UNSIGNED NOT NULL,
    route_id INT UNSIGNED NOT NULL,
    cargo_name VARCHAR(80) NOT NULL,
    route_name VARCHAR(300) NOT NULL,
    distance10 INT NOT NULL CHECK (distance10 BETWEEN 1 AND 100000),
    distance_custom BOOLEAN NOT NULL DEFAULT 0,
    rate_cents INT NOT NULL CHECK (rate_cents BETWEEN 1 AND 100000),
    factor_pct INT NOT NULL CHECK (factor_pct BETWEEN 50 AND 300),
    fee_cents INT NOT NULL CHECK (fee_cents BETWEEN 0 AND 10000000),
    minimum_cents INT NOT NULL CHECK (minimum_cents BETWEEN 0 AND 10000000),
    transport_cents BIGINT NOT NULL CHECK (transport_cents >= 0),
    minimum_extra_cents BIGINT NOT NULL CHECK (minimum_extra_cents >= 0),
    total_cents BIGINT NOT NULL,
    note VARCHAR(200) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cargo_id) REFERENCES cargo_types(id) ON DELETE RESTRICT,
    FOREIGN KEY (route_id) REFERENCES routes(id) ON DELETE RESTRICT,
    INDEX ix_quotes_date (created_at,id),
    INDEX ix_quotes_cargo (cargo_id,id),
    CHECK (total_cents = transport_cents + fee_cents + minimum_extra_cents)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Навчальні тарифи та відстані; не є комерційною пропозицією чи даними навігатора.
INSERT INTO cargo_types (name,rate_cents,minimum_cents,description) VALUES
('Звичайний вантаж',2800,100000,'Палетовані товари без спеціальних умов'),
('Крихкий вантаж',3600,150000,'Скло, електроніка та товари з обережним поводженням'),
('Охолоджений вантаж',4200,200000,'Перевезення з підтриманням температури'),
('Негабаритний вантаж',5500,300000,'Вантаж зі спеціальними вимогами до транспорту');
INSERT INTO routes (origin,destination,via,distance10,factor_pct,fee_cents) VALUES
('Київ','Львів','Житомир · Рівне',5400,100,0),
('Київ','Одеса','Біла Церква · Умань',4750,105,20000),
('Дніпро','Харків','Перещепине',2200,110,10000),
('Київ','Львів','Вінниця · Тернопіль',5750,95,0);
