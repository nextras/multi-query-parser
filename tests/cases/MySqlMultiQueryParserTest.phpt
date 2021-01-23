<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\MultiQueryParser;

use Nextras\MultiQueryParser\MySqlMultiQueryParser;
use Tester\Assert;
use Tester\FileMock;
use Tester\TestCase;


require_once __DIR__ . '/../bootstrap.php';


class MySqlMultiQueryParserTest extends TestCase
{
	/**
	 * @dataProvider provideLoadFileData
	 */
	public function testLoadFile($content, array $expectedQueries)
	{
		$parser = new MySqlMultiQueryParser();
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
					'SELECT 1'
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
				]
			],
			[
				implode("\n", [
					'SELECT 1;',
					'DELIMITER ;;',
					'DELIMITER ;',
				]),
				[
					"SELECT 1",
				]
			],
		];
	}
}


(new MySqlMultiQueryParserTest())->run();
