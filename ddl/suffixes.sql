CREATE TABLE suffixes (
	suffix text NOT NULL,
	create_time timestamp,
    create_id   varchar(100),
	update_time timestamp,
    update_id   varchar(100),
	PRIMARY KEY (suffix)
);

INSERT INTO suffixes (suffix, create_time, create_id) VALUES ('', now(), 'system');
INSERT INTO suffixes (suffix, create_time, create_id) VALUES ('ちゃん', now(), 'system');
INSERT INTO suffixes (suffix, create_time, create_id) VALUES ('さん', now(), 'system');
