CREATE TABLE names (
	name        text NOT NULL,
	cast_id     int NOT NULL,
	create_time timestamp,
    create_id   varchar(100),
	update_time timestamp,
    update_id   varchar(100),
	PRIMARY KEY (name)
);
