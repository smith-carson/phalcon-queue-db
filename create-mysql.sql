CREATE TABLE jobs (
  id       INT PRIMARY KEY      AUTO_INCREMENT,
  tube     VARCHAR(50) NOT NULL DEFAULT 'default',
  body     TEXT        NULL,
  ttr      INT         NULL DEFAULT 0,
  delay    INT         NULL DEFAULT 0,
  priority INT         NULL DEFAULT 0
);