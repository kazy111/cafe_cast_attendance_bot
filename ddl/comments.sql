CREATE TABLE comments (
	comment_date date NOT NULL,
	comment      text NOT NULL,
	create_time  timestamp,
	create_id    varchar(100),
	update_time  timestamp,
	update_id    varchar(100),
	PRIMARY KEY (comment_date)
);
