-- CatatKan — skema database (MySQL 5.7+ / MariaDB 10.3+)
--
-- Jalankan lewat phpMyAdmin (tab "Import") atau: mysql -u root -p < database/schema.sql
-- Catatan hosting gratis (mis. InfinityFree): database dibuat dari panel hosting,
-- jadi HAPUS dua baris CREATE DATABASE / USE di bawah sebelum import.

CREATE DATABASE IF NOT EXISTS catatkan CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE catatkan;

CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(60)  NOT NULL,
  email         VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  is_demo       TINYINT(1)   NOT NULL DEFAULT 0,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Nominal disimpan sebagai BIGINT dalam rupiah penuh (tanpa desimal) agar bebas error pembulatan.
CREATE TABLE IF NOT EXISTS transactions (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED    NOT NULL,
  name       VARCHAR(80)     NOT NULL,
  amount     BIGINT UNSIGNED NOT NULL,
  type       ENUM('income','expense') NOT NULL,
  category   VARCHAR(40)     NOT NULL,
  tx_date    DATE            NOT NULL,
  note       VARCHAR(255)    NULL,
  created_at TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_tx_user_date (user_id, tx_date),
  KEY idx_tx_user_type_cat (user_id, type, category),
  CONSTRAINT fk_tx_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS budgets (
  id            INT UNSIGNED    NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED    NOT NULL,
  category      VARCHAR(40)     NOT NULL,
  monthly_limit BIGINT UNSIGNED NOT NULL,
  UNIQUE KEY uq_budget_user_cat (user_id, category),
  CONSTRAINT fk_budget_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS goals (
  user_id       INT UNSIGNED    NOT NULL PRIMARY KEY,
  title         VARCHAR(80)     NOT NULL DEFAULT 'Dana darurat',
  target_amount BIGINT UNSIGNED NOT NULL DEFAULT 10000000,
  saved_amount  BIGINT UNSIGNED NOT NULL DEFAULT 0,
  updated_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_goal_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pembatasan percobaan login (anti brute-force)
CREATE TABLE IF NOT EXISTS login_attempts (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  email        VARCHAR(190) NOT NULL,
  ip           VARCHAR(45)  NOT NULL,
  attempted_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_attempt_email (email, attempted_at),
  KEY idx_attempt_ip (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
