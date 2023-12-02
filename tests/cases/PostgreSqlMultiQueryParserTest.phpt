<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace Nextras\MultiQueryParser;

use Tester\Assert;
use Tester\TestCase;


require_once __DIR__ . '/../bootstrap.php';


class PostgreSqlMultiQueryParserTest extends TestCase
{
	public function testFile()
	{
		$parser = new PostgreSqlMultiQueryParser();
		$queries = iterator_to_array($parser->parseFile(__DIR__ . '/data/postgres.sql'));
		Assert::count(66, $queries);
		Assert::same("CREATE FUNCTION \"book_collections_before\"() RETURNS TRIGGER AS
\$BODY$
BEGIN
    NEW.\"updated_at\" = NOW();
    return NEW;
END;
\$BODY$
    LANGUAGE 'plpgsql' VOLATILE", $queries[16]);
	}
}


(new PostgreSqlMultiQueryParserTest())->run();
