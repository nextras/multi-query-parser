<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace Nextras\MultiQueryParser;

use LogicException;
use Tester\Assert;
use Tester\TestCase;


require_once __DIR__ . '/../bootstrap.php';


class MySqlMultiQueryParserTest extends TestCase
{
	/**
	 * @dataProvider provideDelimitersData
	 * @param list<string> $expectedQueries
	 */
	public function testDelimiter(string $content, array $expectedQueries): void
	{
		$parser = new MySqlMultiQueryParser();
		$queries = iterator_to_array($parser->parseString($content));
		Assert::same($expectedQueries, $queries);
	}


	public function testFile(): void
	{
		$parser = new MySqlMultiQueryParser();
		$queries = iterator_to_array($parser->parseFile(__DIR__ . '/data/mysql.sql'));
		Assert::count(58, $queries);
	}


	public function testFileWithRandomizedChunking(): void
	{
		$content = file_get_contents(__DIR__ . '/data/mysql.sql');

		if ($content === false) {
			throw new LogicException('Failed to read file content');
		}

		$parser = new MySqlMultiQueryParser();
		$expected = iterator_to_array($parser->parseString($content));

		for ($i = 0; $i < 100; $i++) {
			$chunks = self::randomChunks($content);
			$queries = iterator_to_array($parser->parseStringStream(new \ArrayIterator($chunks)));
			Assert::same($expected, $queries, "Failed with chunk sizes: " . implode(', ', array_map('strlen', $chunks)));
		}
	}


	/**
	 * @return list<string>
	 */
	private static function randomChunks(string $s): array
	{
		$chunks = [];
		$offset = 0;
		$len = strlen($s);
		while ($offset < $len) {
			$size = random_int(1, max(1, min(256, $len - $offset)));
			$chunks[] = substr($s, $offset, $size);
			$offset += $size;
		}
		return $chunks;
	}


	/**
	 * @return list<array{string, list<string>}>
	 */
	protected function provideDelimitersData(): array
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
