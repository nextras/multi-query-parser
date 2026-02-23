Nextras Multi Query Parser
==========================

[![Build](https://github.com/nextras/multi-query-parser/actions/workflows/build.yml/badge.svg)](https://github.com/nextras/multi-query-parser/actions/workflows/build.yml)
[![Downloads this Month](https://img.shields.io/packagist/dm/nextras/multi-query-parser.svg?style=flat)](https://packagist.org/packages/nextras/multi-query-parser)
[![Stable Version](https://img.shields.io/packagist/v/nextras/multi-query-parser.svg?style=flat)](https://packagist.org/packages/nextras/multi-query-parser)

A PHP library for splitting multi-query SQL files into individual statements. Handles database-specific syntax like custom delimiters, dollar-quoted strings, and `BEGIN...END` blocks. Processes files in chunks for memory efficiency.

### Supported Databases

- **MySQL** -- backtick identifiers, `DELIMITER` command, `#` comments
- **PostgreSQL** -- dollar-quoted strings (`$BODY$...$BODY$`), `E'...'` escape strings
- **SQL Server** -- `[bracketed]` identifiers, `BEGIN...END` blocks

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

Available parsers: `MySqlMultiQueryParser`, `PostgreSqlMultiQueryParser`, `SqlServerMultiQueryParser`.

### License

MIT. See full [license](license.md).
