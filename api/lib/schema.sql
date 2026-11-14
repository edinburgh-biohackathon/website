-- Edinburgh BioHackathon backend schema (MariaDB 10.6)
CREATE TABLE IF NOT EXISTS challenges (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  status           ENUM('new','accepted','rejected') NOT NULL DEFAULT 'new',
  name             VARCHAR(200)  NOT NULL,
  organisation     VARCHAR(200)  NOT NULL,
  email            VARCHAR(254)  NOT NULL,
  role             VARCHAR(200)  NOT NULL DEFAULT '',
  submitter_type   VARCHAR(64)   NOT NULL,
  challenge_title  VARCHAR(160)  NOT NULL,
  track            VARCHAR(64)   NOT NULL DEFAULT '',
  backgrounds      TEXT          NOT NULL,   -- JSON array
  description      TEXT          NOT NULL,
  expected_outcome TEXT          NOT NULL,
  resources        TEXT          NOT NULL,
  mentoring        VARCHAR(100)  NOT NULL,
  judging          VARCHAR(16)   NOT NULL,
  nonconfidential  VARCHAR(100)  NOT NULL,
  notes            TEXT          NOT NULL,
  created_at       DATETIME      NOT NULL,
  KEY idx_status (status),
  KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  bucket     VARCHAR(32) NOT NULL,
  key_hash   CHAR(64)    NOT NULL,
  created_at DATETIME    NOT NULL,
  KEY idx_lookup (bucket, key_hash, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=ascii;
