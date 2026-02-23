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
	/**
	 * @dataProvider provideSuperfluousSemicolonsData
	 * @param list<string> $expectedQueries
	 */
	public function testSuperfluousSemicolons(string $content, array $expectedQueries): void
	{
		$parser = new PostgreSqlMultiQueryParser();
		$queries = iterator_to_array($parser->parseString($content));
		Assert::same($expectedQueries, $queries);
	}


	/**
	 * @return list<array{string, list<string>}>
	 */
	protected function provideSuperfluousSemicolonsData(): array
	{
		return [
			[
				'SELECT 1 AS semicolon_madness;;;',
				['SELECT 1 AS semicolon_madness'],
			],
			[
				';;',
				[],
			],
			[
				';;;',
				[],
			],
			[
				';SELECT 1;',
				['SELECT 1'],
			],
			[
				'SELECT 1;;SELECT 2;',
				['SELECT 1', 'SELECT 2'],
			],
			[
				'SELECT 1; ; SELECT 2;',
				['SELECT 1', 'SELECT 2'],
			],
		];
	}


	public function testFile(): void
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
