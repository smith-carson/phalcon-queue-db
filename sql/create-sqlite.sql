CREATE TABLE jobs (
    id       INT PRIMARY KEY,
    tube     TEXT NOT NULL DEFAULT 'default',
    body     TEXT,
    ttr      INT  NOT NULL DEFAULT 0,
    delay    INT  NOT NULL DEFAULT 0,
    priority INT  NOT NULL DEFAULT 0,
    reserved INT  NOT NULL DEFAULT 0,
    buried   INT  NOT NULL DEFAULT 0
);
