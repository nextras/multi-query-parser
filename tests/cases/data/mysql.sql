CREATE TABLE authors
(
	id                 int          NOT NULL AUTO_INCREMENT,
	name               varchar(50)  NOT NULL,
	web                varchar(100) NOT NULL,
	born               date DEFAULT NULL,
	favorite_author_id int,
	PRIMARY KEY (id),
	CONSTRAINT authors_favorite_author FOREIGN KEY (favorite_author_id) REFERENCES authors (id)
) AUTO_INCREMENT = 2;


CREATE TABLE publishers
(
	publisher_id int         NOT NULL AUTO_INCREMENT,
	name         varchar(50) NOT NULL,
	PRIMARY KEY (publisher_id)
) AUTO_INCREMENT = 1;


CREATE TABLE tags
(
	id        int         NOT NULL AUTO_INCREMENT,
	name      varchar(50) NOT NULL,
	is_global char(1)     NOT NULL,
	PRIMARY KEY (id)
) AUTO_INCREMENT = 4;

CREATE TABLE eans
(
	id   int         NOT NULL AUTO_INCREMENT,
	code varchar(50) NOT NULL,
	type int         NOT NULL,
	PRIMARY KEY (id)
) AUTO_INCREMENT = 1;

CREATE TABLE books
(
	id                  int         NOT NULL AUTO_INCREMENT,
	author_id           int         NOT NULL,
	translator_id       int,
	title               varchar(50) NOT NULL,
	next_part           int,
	publisher_id        int         NOT NULL,
	published_at        DATETIME    NOT NULL,
	printed_at          DATETIME,
	ean_id              int,
	price               int,
	price_currency      char(3),
	orig_price_cents    int,
	orig_price_currency char(3),
	PRIMARY KEY (id),
	CONSTRAINT books_authors FOREIGN KEY (author_id) REFERENCES authors (id),
	CONSTRAINT books_translator FOREIGN KEY (translator_id) REFERENCES authors (id),
	CONSTRAINT books_next_part FOREIGN KEY (next_part) REFERENCES books (id),
	CONSTRAINT books_publisher FOREIGN KEY (publisher_id) REFERENCES publishers (publisher_id),
	CONSTRAINT books_ena FOREIGN KEY (ean_id) REFERENCES eans (id)
) AUTO_INCREMENT = 4;

CREATE INDEX book_title ON books (title);


CREATE TABLE books_x_tags
(
	book_id int NOT NULL,
	tag_id  int NOT NULL,
	PRIMARY KEY (book_id, tag_id),
	CONSTRAINT books_x_tags_tag FOREIGN KEY (tag_id) REFERENCES tags (id),
	CONSTRAINT books_x_tags_book FOREIGN KEY (book_id) REFERENCES books (id) ON DELETE CASCADE
);


CREATE TABLE tag_followers
(
	tag_id     int       NOT NULL,
	author_id  int       NOT NULL,
	created_at timestamp NOT NULL,
	PRIMARY KEY (tag_id, author_id),
	CONSTRAINT tag_followers_tag FOREIGN KEY (tag_id) REFERENCES tags (id) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT tag_followers_author FOREIGN KEY (author_id) REFERENCES authors (id) ON DELETE CASCADE ON UPDATE CASCADE
);


CREATE TABLE contents
(
	id         int         NOT NULL AUTO_INCREMENT,
	type       varchar(10) NOT NULL,
	thread_id  int,
	replied_at timestamp,
	PRIMARY KEY (id),
	CONSTRAINT contents_parent_id FOREIGN KEY (thread_id) REFERENCES contents (id) ON DELETE CASCADE ON UPDATE CASCADE
);


CREATE TABLE book_collections
(
	id         int UNSIGNED NOT NULL,
	name       varchar(255) NOT NULL,
	updated_at datetime     NULL,
	PRIMARY KEY (`id`)
);


CREATE TABLE photo_albums
(
	id         int          NOT NULL AUTO_INCREMENT,
	title      varchar(255) NOT NULL,
	preview_id int          NULL,
	PRIMARY KEY (id)
) AUTO_INCREMENT = 1;


CREATE TABLE photos
(
	id       int          NOT NULL AUTO_INCREMENT,
	title    varchar(255) NOT NULL,
	album_id int          NOT NULL,
	PRIMARY KEY (id),
	CONSTRAINT photos_album_id FOREIGN KEY (album_id) REFERENCES photo_albums (id) ON DELETE CASCADE ON UPDATE CASCADE
) AUTO_INCREMENT = 1;


ALTER TABLE photo_albums
	ADD CONSTRAINT photo_albums_preview_id FOREIGN KEY (preview_id) REFERENCES photos (id) ON DELETE CASCADE ON UPDATE CASCADE;


CREATE TABLE users
(
	id int NOT NULL AUTO_INCREMENT,
	PRIMARY KEY (id)
);


CREATE TABLE user_stats
(
	user_id int       NOT NULL,
	date    TIMESTAMP NOT NULL,
	value   int       NOT NULL,
	PRIMARY KEY (user_id, date),
	CONSTRAINT user_stats_user_id FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE
);


CREATE TABLE users_x_users
(
	my_friends_id      int NOT NULL,
	friends_with_me_id int NOT NULL,
	PRIMARY KEY (my_friends_id, friends_with_me_id),
	CONSTRAINT my_friends_key FOREIGN KEY (my_friends_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT friends_with_me_key FOREIGN KEY (friends_with_me_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
);


CREATE TRIGGER `book_collections_bu_trigger`
	BEFORE UPDATE
	ON `book_collections`
	FOR EACH ROW SET NEW.updated_at = NOW();

CREATE TRIGGER `book_collections_bi_trigger`
	BEFORE INSERT
	ON `book_collections`
	FOR EACH ROW SET NEW.updated_at = NOW();


CREATE TABLE logs
(
	date  TIMESTAMP NOT NULL,
	count INT       NOT NULL,
	PRIMARY KEY (date)
);


CREATE TABLE publishers_x_tags
(
	publisher_id int NOT NULL,
	tag_id  int NOT NULL,
	PRIMARY KEY (publisher_id, tag_id),
	CONSTRAINT publishers_x_tags_tag FOREIGN KEY (tag_id) REFERENCES tags (id),
	CONSTRAINT publishers_x_tags_publisher FOREIGN KEY (publisher_id) REFERENCES publishers (publisher_id) ON DELETE CASCADE
);

SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE books_x_tags;
TRUNCATE publishers_x_tags;
TRUNCATE books;
TRUNCATE eans;
TRUNCATE tags;
TRUNCATE authors;
TRUNCATE publishers;
TRUNCATE tag_followers;
TRUNCATE contents;
TRUNCATE users_x_users;
TRUNCATE user_stats;
TRUNCATE users;
TRUNCATE logs;
SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO authors (id, name, web, born) VALUES (1, 'Writer 1', 'http://example.com/1', NULL);
INSERT INTO authors (id, name, web, born) VALUES (2, 'Writer 2', 'http://example.com/2', NULL);

INSERT INTO publishers (publisher_id, name) VALUES (1, 'Nextras publisher A');
INSERT INTO publishers (publisher_id, name) VALUES (2, 'Nextras publisher B');
INSERT INTO publishers (publisher_id, name) VALUES (3, 'Nextras publisher C');

INSERT INTO tags (id, name, is_global) VALUES (1, 'Tag 1', 'y');
INSERT INTO tags (id, name, is_global) VALUES (2, 'Tag 2', 'y');
INSERT INTO tags (id, name, is_global) VALUES (3, 'Tag 3', 'n');

INSERT INTO books (id, author_id, translator_id, title, next_part, publisher_id, published_at, price, price_currency) VALUES (1, 1, 1, 'Book 1', NULL, 1, '2021-12-14 21:10:04', 50, 'CZK');
INSERT INTO books (id, author_id, translator_id, title, next_part, publisher_id, published_at, price, price_currency) VALUES (2, 1, NULL, 'Book 2', NULL, 2, '2021-12-14 21:10:02', 150, 'CZK');
INSERT INTO books (id, author_id, translator_id, title, next_part, publisher_id, published_at, price, price_currency) VALUES (3, 2, 2, 'Book 3', NULL, 3, '2021-12-14 21:10:03', 20, 'CZK');
INSERT INTO books (id, author_id, translator_id, title, next_part, publisher_id, published_at, price, price_currency) VALUES (4, 2, 2, 'Book 4', 3, 1, '2021-12-14 21:10:01', 220, 'CZK');

INSERT INTO books_x_tags (book_id, tag_id) VALUES (1, 1);
INSERT INTO books_x_tags (book_id, tag_id) VALUES (1, 2);
INSERT INTO books_x_tags (book_id, tag_id) VALUES (2, 2);
INSERT INTO books_x_tags (book_id, tag_id) VALUES (2, 3);
INSERT INTO books_x_tags (book_id, tag_id) VALUES (3, 3);

INSERT INTO tag_followers (tag_id, author_id, created_at) VALUES (1, 1, '2014-01-01 00:10:00');
INSERT INTO tag_followers (tag_id, author_id, created_at) VALUES (3, 1, '2014-01-02 00:10:00');
INSERT INTO tag_followers (tag_id, author_id, created_at) VALUES (2, 2, '2014-01-03 00:10:00');

INSERT INTO contents (id, type, thread_id, replied_at) VALUES (1, 'thread', NULL, NULL);
INSERT INTO contents (id, type, thread_id, replied_at) VALUES (2, 'comment', 1, '2020-01-01 12:00:00');
INSERT INTO contents (id, type, thread_id, replied_at) VALUES (3, 'comment', 1, '2020-01-02 12:00:00');
