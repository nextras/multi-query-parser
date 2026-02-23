<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace Nextras\MultiQueryParser;

use Tester\Assert;
use Tester\TestCase;


require_once __DIR__ . '/../bootstrap.php';


class SqlServerMultiQueryParserTest extends TestCase
{
	public function testFile(): void
	{
		$parser = new SqlServerMultiQueryParser();
		$queries = iterator_to_array($parser->parseFile(__DIR__ . '/data/sqlserver.sql'));
		Assert::count(69, $queries);
		Assert::same("CREATE TRIGGER mydatabase.trigger_book_stats
	ON yourtable.books
	AFTER INSERT, DELETE
	AS
BEGIN
	SET NOCOUNT ON;
	INSERT INTO yourtable.book_stats(
		book_id,
		string_value
	)
	SELECT
		i.book_id,
		'INS'
	FROM
		inserted i
	UNION ALL
	SELECT
		d.book_id,
		'DEL'
	FROM
		deleted d;
END", $queries[67]);
	}
}


(new SqlServerMultiQueryParserTest())->run();
