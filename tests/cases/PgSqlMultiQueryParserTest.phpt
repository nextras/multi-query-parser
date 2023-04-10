<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace Nextras\MultiQueryParser;

use Tester\Assert;
use Tester\FileMock;
use Tester\TestCase;


require_once __DIR__ . '/../bootstrap.php';


class PgSqlMultiQueryParserTest extends TestCase
{
	/**
	 * @dataProvider provideLoadFileData
	 */
	public function testLoadFile($content, array $expectedQueries)
	{
		$parser = new PostgreSqlMultiQueryParser();
		$actualQueries = iterator_to_array($parser->parseFile(FileMock::create($content)));
		Assert::same($expectedQueries, $actualQueries);
	}


	protected function provideLoadFileData()
	{
		return [
			[
				'SELECT 1',
				[
					'SELECT 1',
				],
			],
			[
				'SELECT 1; ',
				[
					'SELECT 1',
				],
			],
			[
				'SELECT 1; SELECT 2;    SELECT 3; ',
				[
					'SELECT 1',
					'SELECT 2',
					'SELECT 3',
				],
			],
			[
				'-- ',
				[],
			],
			[
				"--\n",
				[],
			],
			[
				"--",
				[],
			],
			[
				"SELECT 1;\n--",
				[
					'SELECT 1',
				],
			],
			[
				"SELECT 1;\n--\nSELECT 2;",
				[
					'SELECT 1',
					'SELECT 2',
				],
			],
		];
	}
}


(new PgSqlMultiQueryParserTest())->run();
