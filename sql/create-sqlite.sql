CREATE TABLE jobs (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    tube       TEXT    NOT NULL DEFAULT 'default',
    body       BLOB,
--    ttr      INTEGER NOT NULL DEFAULT 0,
    created_at INTEGER NOT NULL,
    delay      INTEGER NOT NULL DEFAULT 0,
    priority   INTEGER NOT NULL DEFAULT 2147483648,
    reserved   INTEGER NOT NULL DEFAULT 0,
    buried     INTEGER NOT NULL DEFAULT 0
);
