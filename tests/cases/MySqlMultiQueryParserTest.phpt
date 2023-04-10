<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace Nextras\MultiQueryParser;

use Tester\Assert;
use Tester\FileMock;
use Tester\TestCase;


require_once __DIR__ . '/../bootstrap.php';


class MySqlMultiQueryParserTest extends TestCase
{
	/**
	 * @dataProvider provideDelimitersData
	 */
	public function testDelimiter($content, array $expectedQueries)
	{
		$parser = new MySqlMultiQueryParser();
		$queries = iterator_to_array($parser->parseFile(FileMock::create($content)));
		Assert::same($expectedQueries, $queries);
	}


	public function testFile()
	{
		$parser = new MySqlMultiQueryParser();
		$queries = iterator_to_array($parser->parseFile(__DIR__ . '/data/mysql.sql'));
		Assert::count(58, $queries);
	}


	protected function provideDelimitersData()
	{
		return [
			[
				implode("\n", [
					'SELECT 1;',
					'DELIMITER //',
					'CREATE TRIGGER `users_bu` BEFORE UPDATE ON `users` FOR EACH ROW BEGIN SELECT 1; END; //',
					'DELIMITER ;',
					'SELECT 2;',
				]),
				[
					'SELECT 1',
					"CREATE TRIGGER `users_bu` BEFORE UPDATE ON `users` FOR EACH ROW BEGIN SELECT 1; END; ",
					"SELECT 2",
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
			[
				implode("\n", [
					'DELIMITER ;;',
					'SELECT 1;;',
					'DELIMITER ;',
					'DELIMITER ;;',
					'SELECT 2;;',
					'DELIMITER ;',
				]),
				[
					'SELECT 1',
					'SELECT 2',
				],
			],
			[
				implode("\n", [
					'SELECT 1;',
					'DELIMITER ;;',
					'DELIMITER ;',
				]),
				[
					"SELECT 1",
				],
			],
		];
	}
}


(new MySqlMultiQueryParserTest())->run();
