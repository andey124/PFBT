CREATE TABLE movies (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  public_id   CHAR(12)     NOT NULL UNIQUE,
  title       VARCHAR(200) NOT NULL,
  year        SMALLINT     NOT NULL,
  screened_on DATE         NOT NULL,
  director    VARCHAR(120) NULL,
  genre       VARCHAR(120) NULL,
  poster_url  VARCHAR(500) NULL,
  note        TEXT         NULL,
  legacy_avg  DECIMAL(3,1) NULL,
  is_open     TINYINT(1)   NOT NULL DEFAULT 1,
  created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) DEFAULT CHARSET=utf8mb4;

CREATE TABLE ratings (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  movie_id   INT         NOT NULL,
  name       VARCHAR(60) NULL,
  score      TINYINT     NOT NULL,
  created_at TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE,
  CHECK (score BETWEEN 1 AND 10)
) DEFAULT CHARSET=utf8mb4;

-- Eine Zeile pro Versuch statt Zaehler: keine Race Condition zwischen
-- parallelen Requests, gleiches SQL unter MySQL und SQLite (Tests).
-- Bestehende Installationen: nur diesen Block nachziehen.
CREATE TABLE rate_limits (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  bucket     CHAR(64) NOT NULL,   -- sha256(scope|ip), keine IP im Klartext
  created_at INT      NOT NULL,   -- Unix-Timestamp, keine Zeitzonen-Fallen
  KEY bucket_time (bucket, created_at)
) DEFAULT CHARSET=utf8mb4;
