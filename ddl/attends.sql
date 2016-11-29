CREATE TABLE attends (
	cast_id     int NOT NULL,
	attend_date date NOT NULL,
	attend_type char(1) NOT NULL,
	create_time timestamp,
    create_id   varchar(100),
	update_time timestamp,
    update_id   varchar(100),
	PRIMARY KEY (cast_id, attend_date)
);
