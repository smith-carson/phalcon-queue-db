CREATE TABLE jobs (
    id       INTEGER PRIMARY KEY AUTOINCREMENT,
    tube     TEXT    NOT NULL DEFAULT 'default',
    body     BLOB,
--    ttr      INTEGER NOT NULL DEFAULT 0,
    delay    INTEGER NOT NULL DEFAULT 0,
    priority INTEGER NOT NULL DEFAULT 2147483648,
    reserved INTEGER NOT NULL DEFAULT 0,
    buried   INTEGER NOT NULL DEFAULT 0
);

-- delayed until 2030-01-01 01:00 and 2015-01-01 01:00
INSERT INTO jobs (tube, body, delay) VALUES ('default', 's:19:"delayed until later";', 1893466800);
INSERT INTO jobs (tube, body, delay) VALUES ('default', 's:18:"delay have expired";', 1420081200);
INSERT INTO jobs (tube, body, buried) VALUES ('default', 's:6:"buried";', 1);
INSERT INTO jobs (tube, body, reserved) VALUES ('default', 's:8:"reserved";', 1);
--INSERT INTO jobs (tube, body, ttr) VALUES ('default', 's:8:"ttr";', 5);

INSERT INTO jobs (tube, body) VALUES ('default', 's:2:"10";');
INSERT INTO jobs (tube, body) VALUES ('default', 's:2:"15";');
INSERT INTO jobs (tube, body) VALUES ('json', 's:10:"{"int":10}";');
INSERT INTO jobs (tube, body) VALUES ('array', 'a:1:{s:3:"int";i:10;}');
INSERT INTO jobs (tube, body) VALUES ('int', 'i:10;');
