Nextras Multi Query Parser
==========================

[![Build](https://github.com/nextras/multi-query-parser/actions/workflows/build.yml/badge.svg)](https://github.com/nextras/multi-query-parser/actions/workflows/build.yml)
[![Downloads this Month](https://img.shields.io/packagist/dm/nextras/multi-query-parser.svg?style=flat)](https://packagist.org/packages/nextras/multi-query-parser)
[![Stable Version](https://img.shields.io/packagist/v/nextras/multi-query-parser.svg?style=flat)](https://packagist.org/packages/nextras/multi-query-parser)

A streaming PHP parser for splitting multi-query SQL files into individual statements. Handles database-specific syntax like custom delimiters, dollar-quoted strings, and `BEGIN...END` blocks. The parser reads input in small chunks and yields statements one by one -- it never loads the entire file into memory, making it suitable for large SQL dumps.

### Supported Databases

- **MySQL** -- backtick identifiers, `DELIMITER` command, `#` comments
- **PostgreSQL** -- dollar-quoted strings (`$BODY$...$BODY$`), `E'...'` escape strings
- **SQL Server** -- `[bracketed]` identifiers, `BEGIN...END` blocks
- **SQLite** -- all three identifier styles (`"double"`, `` `backtick` ``, `[bracket]`), `BEGIN...END` blocks for triggers

All parsers handle standard SQL comments (`--`, `/* */`), quoted strings, and semicolon delimiters.

### Installation

```bash
composer require nextras/multi-query-parser
```

Requires PHP 8.0+.

### Usage

**Parse a SQL file:**

```php
use Nextras\MultiQueryParser\MySqlMultiQueryParser;

$parser = new MySqlMultiQueryParser();

foreach ($parser->parseFile('migrations.sql') as $query) {
    $connection->query($query);
}
```

**Parse a string:**

```php
$sql = "CREATE TABLE users (id INT); INSERT INTO users VALUES (1);";

foreach ($parser->parseString($sql) as $query) {
    $connection->query($query);
}
```

**Parse from a file stream:**

```php
$stream = fopen('migrations.sql', 'r');

foreach ($parser->parseFileStream($stream) as $query) {
    $connection->query($query);
}
```

Available parsers: `MySqlMultiQueryParser`, `PostgreSqlMultiQueryParser`, `SqlServerMultiQueryParser`, `SqliteMultiQueryParser`.

**Keep leading comments:**

By default, comments are stripped and only query strings are yielded. To control what happens to
comments, pass a `CommentStrategy` to the parser constructor. The bundled `PrependLeadingComments`
strategy keeps the comments preceding a query as a prefix of that query -- useful when comments
carry meaningful annotations, e.g. so they remain visible in observability tools:

```php
use Nextras\MultiQueryParser\Strategy\PrependLeadingComments;

$parser = new MySqlMultiQueryParser(new PrependLeadingComments());

$sql = "-- create the users table\nCREATE TABLE users (id INT);";

foreach ($parser->parseString($sql) as $query) {
    echo $query; // "-- create the users table\nCREATE TABLE users (id INT)"
}
```

All comment styles supported by the given dialect (`--`, `/* */`, and `#` for MySQL) that directly precede a query are preserved with their original formatting; only pure leading whitespace is stripped. A comment that sits between two queries is treated as preceding the following one. Comments not followed by any query (e.g. a trailing comment at the end of input) are dropped.

**Custom comment handling:**

Internally a parser tokenizes the input into a stream of `Query` and `Comment` fragments; the
`CommentStrategy` collapses that stream into the final query strings. The default `DropComments`
strategy discards every comment. To implement a different policy (for example, requiring a blank
line between a comment and its query, or appending trailing comments), implement `CommentStrategy`
yourself:

```php
use Iterator;
use Nextras\MultiQueryParser\CommentStrategy;
use Nextras\MultiQueryParser\Fragment\Query;

final class MyCommentStrategy implements CommentStrategy
{
    public function apply(Iterator $fragments): Iterator
    {
        foreach ($fragments as $fragment) {
            if ($fragment instanceof Query) {
                yield $fragment->sql;
            }
            // decide what to do with Comment fragments
        }
    }
}

$parser = new MySqlMultiQueryParser(new MyCommentStrategy());
```

### License

MIT. See full [license](license.md).
