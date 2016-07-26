CREATE TABLE jobs (
    id         INT          PRIMARY KEY AUTO_INCREMENT,
    tube       VARCHAR(50)  NOT NULL DEFAULT 'default',
    body       BLOB         NULL,
    created_at INT          NOT NULL,
--    ttr      INT          NOT NULL DEFAULT 0,
    delay      INT          NOT NULL DEFAULT 0,
    priority   INT UNSIGNED NOT NULL DEFAULT 2147483648,
    reserved   INT          NOT NULL DEFAULT 0,
    buried     INT          NOT NULL DEFAULT 0
);
