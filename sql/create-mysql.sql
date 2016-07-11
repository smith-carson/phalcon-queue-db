CREATE TABLE jobs (
    id       INT PRIMARY KEY      AUTO_INCREMENT,
    tube     VARCHAR(50) NOT NULL DEFAULT 'default',
    body     TEXT        NULL,
    ttr      INT         NOT NULL DEFAULT 0,
    delay    INT         NOT NULL DEFAULT 0,
    priority INT         NOT NULL DEFAULT 0,
    reserved INT         NOT NULL DEFAULT 0,
    buried   INT         NOT NULL DEFAULT 0
);
