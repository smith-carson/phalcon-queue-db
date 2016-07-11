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

INSERT INTO jobs (tube, body) VALUES ('json', 's:10:"{"int":10}";');
INSERT INTO jobs (tube, body) VALUES ('array', 'a:1:{s:3:"int";i:10;}');
INSERT INTO jobs (tube, body) VALUES ('int', 'i:10;');

INSERT INTO jobs (tube, body) VALUES ('default', 's:2:"10";');
INSERT INTO jobs (tube, body) VALUES ('default', 's:2:"15";');
INSERT INTO jobs (tube, body, buried) VALUES ('default', 's:6:"buried";', 1);
INSERT INTO jobs (tube, body, reserved) VALUES ('default', 's:6:"reserved";', 1);
INSERT INTO jobs (tube, body, delay) VALUES ('default', 's:6:"delay";', 1893466800); -- 2030-01-01 01:00
