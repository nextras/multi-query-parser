CREATE TABLE authors
(
	id                 INTEGER      NOT NULL,
	name               TEXT         NOT NULL,
	web                TEXT         NOT NULL,
	born               TEXT DEFAULT NULL,
	favorite_author_id INTEGER,
	PRIMARY KEY (id AUTOINCREMENT),
	FOREIGN KEY (favorite_author_id) REFERENCES authors (id)
);


CREATE TABLE publishers
(
	publisher_id INTEGER NOT NULL,
	name         TEXT    NOT NULL,
	PRIMARY KEY (publisher_id AUTOINCREMENT)
);


CREATE TABLE tags
(
	id        INTEGER NOT NULL,
	name      TEXT    NOT NULL,
	is_global TEXT    NOT NULL,
	PRIMARY KEY (id AUTOINCREMENT)
);

CREATE TABLE eans
(
	id   INTEGER NOT NULL,
	code TEXT    NOT NULL,
	type INTEGER NOT NULL,
	PRIMARY KEY (id AUTOINCREMENT)
);

CREATE TABLE books
(
	id                  INTEGER NOT NULL,
	author_id           INTEGER NOT NULL,
	translator_id       INTEGER,
	title               TEXT    NOT NULL,
	next_part           INTEGER,
	publisher_id        INTEGER NOT NULL,
	published_at        TEXT    NOT NULL,
	printed_at          TEXT,
	ean_id              INTEGER,
	price               INTEGER,
	price_currency      TEXT,
	orig_price_cents    INTEGER,
	orig_price_currency TEXT,
	PRIMARY KEY (id AUTOINCREMENT),
	FOREIGN KEY (author_id) REFERENCES authors (id),
	FOREIGN KEY (translator_id) REFERENCES authors (id),
	FOREIGN KEY (next_part) REFERENCES books (id),
	FOREIGN KEY (publisher_id) REFERENCES publishers (publisher_id),
	FOREIGN KEY (ean_id) REFERENCES eans (id)
);

CREATE INDEX book_title ON books (title);


CREATE TABLE books_x_tags
(
	book_id INTEGER NOT NULL,
	tag_id  INTEGER NOT NULL,
	PRIMARY KEY (book_id, tag_id),
	FOREIGN KEY (tag_id) REFERENCES tags (id),
	FOREIGN KEY (book_id) REFERENCES books (id) ON DELETE CASCADE
);


CREATE TABLE tag_followers
(
	tag_id     INTEGER NOT NULL,
	author_id  INTEGER NOT NULL,
	created_at TEXT    NOT NULL,
	PRIMARY KEY (tag_id, author_id),
	FOREIGN KEY (tag_id) REFERENCES tags (id) ON DELETE CASCADE,
	FOREIGN KEY (author_id) REFERENCES authors (id) ON DELETE CASCADE
);


CREATE TABLE contents
(
	id         INTEGER NOT NULL,
	type       TEXT    NOT NULL,
	thread_id  INTEGER,
	replied_at TEXT,
	PRIMARY KEY (id),
	FOREIGN KEY (thread_id) REFERENCES contents (id)
);


CREATE TABLE book_collections
(
	id         INTEGER NOT NULL,
	name       TEXT    NOT NULL,
	updated_at TEXT    NULL,
	PRIMARY KEY (id)
);


CREATE TABLE photo_albums
(
	id         INTEGER NOT NULL,
	title      TEXT    NOT NULL,
	preview_id INTEGER NULL,
	PRIMARY KEY (id AUTOINCREMENT)
);


CREATE TABLE photos
(
	id       INTEGER NOT NULL,
	title    TEXT    NOT NULL,
	album_id INTEGER NOT NULL,
	PRIMARY KEY (id AUTOINCREMENT),
	FOREIGN KEY (album_id) REFERENCES photo_albums (id) ON DELETE CASCADE
);


CREATE TABLE users
(
	id INTEGER NOT NULL,
	PRIMARY KEY (id AUTOINCREMENT)
);


CREATE TABLE user_stats
(
	user_id INTEGER NOT NULL,
	date    TEXT    NOT NULL,
	value   INTEGER NOT NULL,
	PRIMARY KEY (user_id, date),
	FOREIGN KEY (user_id) REFERENCES users (id)
);


CREATE TABLE users_x_users
(
	my_friends_id      INTEGER NOT NULL,
	friends_with_me_id INTEGER NOT NULL,
	PRIMARY KEY (my_friends_id, friends_with_me_id),
	FOREIGN KEY (my_friends_id) REFERENCES users (id) ON DELETE CASCADE,
	FOREIGN KEY (friends_with_me_id) REFERENCES users (id)
);


CREATE TABLE logs
(
	date  TEXT    NOT NULL,
	count INTEGER NOT NULL,
	PRIMARY KEY (date)
);


CREATE TABLE publishers_x_tags
(
	publisher_id INTEGER NOT NULL,
	tag_id       INTEGER NOT NULL,
	PRIMARY KEY (publisher_id, tag_id),
	FOREIGN KEY (tag_id) REFERENCES tags (id),
	FOREIGN KEY (publisher_id) REFERENCES publishers (publisher_id) ON DELETE CASCADE
);

PRAGMA foreign_keys = ON;

CREATE VIEW active_authors AS
SELECT a.id, a.name FROM authors a
WHERE EXISTS (SELECT 1 FROM books b WHERE b.author_id = a.id);

CREATE TRIGGER trigger_book_collections_update
	AFTER UPDATE ON book_collections
	FOR EACH ROW
BEGIN
	UPDATE book_collections SET updated_at = datetime('now') WHERE id = NEW.id;
END;

CREATE TRIGGER trigger_book_collections_insert
	AFTER INSERT ON book_collections
	FOR EACH ROW
BEGIN
	UPDATE book_collections SET updated_at = datetime('now') WHERE id = NEW.id;
END;

DELETE FROM books_x_tags;
DELETE FROM publishers_x_tags;
DELETE FROM books;
DELETE FROM eans;
DELETE FROM tags;
DELETE FROM authors;
DELETE FROM publishers;
DELETE FROM tag_followers;
DELETE FROM contents;
DELETE FROM users_x_users;
DELETE FROM user_stats;
DELETE FROM users;
DELETE FROM logs;

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

INSERT INTO `books_x_tags` (`book_id`, `tag_id`) VALUES (1, 1);
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

INSERT INTO authors (id, name, web, born) VALUES (99, 'it''s a; test', 'http://example.com/99', NULL);

/* Block comment with ; semicolons inside */
SELECT [bracket;identifier] FROM authors;
SELECT [escaped]]bracket] FROM authors;
SELECT "double;quoted" FROM authors;
SELECT `backtick;quoted` FROM authors;
