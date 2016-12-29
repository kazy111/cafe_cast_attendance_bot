CREATE TABLE casts (
	cast_id     serial NOT NULL,
	display_name text,
	birthday    date,
	color       text,
	create_time timestamp,
    create_id   varchar(100),
	update_time timestamp,
    update_id   varchar(100),
	url         text,
	PRIMARY KEY (cast_id)
);
