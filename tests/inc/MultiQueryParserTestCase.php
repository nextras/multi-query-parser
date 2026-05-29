<?php declare(strict_types = 1);

namespace Nextras\MultiQueryParser;

use LogicException;
use Nextras\MultiQueryParser\Exception\RuntimeException;
use Tester\Assert;
use Tester\TestCase;


abstract class MultiQueryParserTestCase extends TestCase
{
	abstract protected function createParser(bool $preserveLeadingComments = false): IMultiQueryParser;


	/**
	 * Returns the path to the SQL data file for file-based tests.
	 */
	abstract protected function getDataFilePath(): string;


	/**
	 * Returns the expected number of queries in the SQL data file.
	 */
	abstract protected function getExpectedFileQueryCount(): int;


	/**
	 * @return list<array{string, list<string>}>
	 */
	abstract protected function provideSuperfluousSemicolonsData(): array;


	/**
	 * @return list<array{string, list<string>}>
	 */
	abstract protected function provideEdgeCasesData(): array;


	/**
	 * @return list<array{list<string>, list<string>}>
	 */
	abstract protected function provideChunkBoundaryData(): array;


	/**
	 * @dataProvider provideSuperfluousSemicolonsData
	 * @param list<string> $expectedQueries
	 */
	public function testSuperfluousSemicolons(string $content, array $expectedQueries): void
	{
		$parser = $this->createParser();
		$queries = iterator_to_array($parser->parseString($content));
		Assert::same($expectedQueries, $queries);
	}


	/**
	 * @dataProvider provideEdgeCasesData
	 * @param list<string> $expectedQueries
	 */
	public function testEdgeCases(string $content, array $expectedQueries): void
	{
		$parser = $this->createParser();
		$queries = iterator_to_array($parser->parseString($content));
		Assert::same($expectedQueries, $queries);
	}


	/**
	 * @dataProvider provideChunkBoundaryData
	 * @param list<string> $chunks
	 * @param list<string> $expectedQueries
	 */
	public function testChunkBoundary(array $chunks, array $expectedQueries): void
	{
		$parser = $this->createParser();
		$queries = iterator_to_array($parser->parseStringStream(new \ArrayIterator($chunks)));
		Assert::same($expectedQueries, $queries);
	}


	/**
	 * Dialect-agnostic leading-comment cases (line and block comments), shared by every
	 * parser. Dialect-specific comment styles are tested in the subclasses.
	 *
	 * @dataProvider provideCommonPreserveLeadingCommentsData
	 * @param list<string> $expectedQueries
	 */
	public function testPreserveLeadingComments(string $content, array $expectedQueries): void
	{
		$parser = $this->createParser(preserveLeadingComments: true);
		$queries = iterator_to_array($parser->parseString($content));
		Assert::same($expectedQueries, $queries);
	}


	/**
	 * The restructured leading-comment pattern must keep streaming chunk-safe:
	 * every two-chunk split of the input must reproduce the whole-string result.
	 */
	public function testPreserveLeadingCommentsChunkBoundary(): void
	{
		$parser = $this->createParser(preserveLeadingComments: true);
		$content = implode("\n", [
			'-- header comment',
			'-- second line',
			'SELECT 1;',
			'',
			'SELECT 2;',
			'/* block ; with semi */',
			'SELECT 3;',
			'SELECT 4; -- trailing',
			'-- leading before 5',
			'SELECT 5;',
		]);

		$expected = iterator_to_array($parser->parseString($content));
		$len = strlen($content);

		for ($i = 0; $i <= $len; $i++) {
			$chunks = [substr($content, 0, $i), substr($content, $i)];
			$queries = iterator_to_array($parser->parseStringStream(new \ArrayIterator($chunks)));
			Assert::same($expected, $queries, "Failed with chunk boundary at offset $i");
		}
	}


	/**
	 * @return list<array{string, list<string>}>
	 */
	protected function provideCommonPreserveLeadingCommentsData(): array
	{
		return [
			// A single -- comment kept as a prefix of the following query
			[
				"-- create the users table\nCREATE TABLE users (id INT);",
				["-- create the users table\nCREATE TABLE users (id INT)"],
			],
			// Multiple consecutive -- comment lines
			[
				"-- line 1\n-- line 2\nSELECT 1;",
				["-- line 1\n-- line 2\nSELECT 1"],
			],
			// Each query keeps only its own leading comment
			[
				"-- first\nSELECT 1;\n-- second\nSELECT 2;",
				["-- first\nSELECT 1", "-- second\nSELECT 2"],
			],
			// A comment between two queries attaches to the following query
			[
				"SELECT 1; -- between\nSELECT 2;",
				["SELECT 1", "-- between\nSELECT 2"],
			],
			// /* */ block comments are preserved too
			[
				"/* block */ SELECT 1;",
				["/* block */ SELECT 1"],
			],
			// Mixed comment types preserve their original formatting
			[
				"-- a\n/* b */\nSELECT 1;",
				["-- a\n/* b */\nSELECT 1"],
			],
			// Pure leading whitespace / blank lines before the comment are stripped
			[
				"\n\n-- spaced\n\nSELECT 1;",
				["-- spaced\n\nSELECT 1"],
			],
			// Comment-only input yields nothing (no query to attach to)
			["-- only a comment", []],
			["-- line 1\n-- line 2\n", []],
			["/* only a block */", []],
			// A trailing comment after the last query (no following query) is dropped
			[
				"SELECT 1;\n-- trailing",
				["SELECT 1"],
			],
			// Pure whitespace produces no leading prefix
			[
				"\n\nSELECT 1;\n\n",
				["SELECT 1"],
			],
		];
	}


	public function testFile(): void
	{
		$parser = $this->createParser();
		$queries = iterator_to_array($parser->parseFile($this->getDataFilePath()));
		Assert::count($this->getExpectedFileQueryCount(), $queries);
	}


	public function testFileWithRandomizedChunking(): void
	{
		$content = file_get_contents($this->getDataFilePath());

		if ($content === false) {
			throw new LogicException('Failed to read file content');
		}

		$parser = $this->createParser();
		$expected = iterator_to_array($parser->parseString($content));

		for ($i = 0; $i < 100; $i++) {
			$chunks = self::randomChunks($content);
			$queries = iterator_to_array($parser->parseStringStream(new \ArrayIterator($chunks)));
			Assert::same($expected, $queries, "Failed with chunk sizes: " . implode(', ', array_map('strlen', $chunks)));
		}
	}


	public function testFileWithAllTwoChunkCombinations(): void
	{
		$content = file_get_contents($this->getDataFilePath());

		if ($content === false) {
			throw new LogicException('Failed to read file content');
		}

		$parser = $this->createParser();
		$expected = iterator_to_array($parser->parseString($content));
		$len = strlen($content);

		for ($i = 0; $i <= $len; $i++) {
			$chunks = [substr($content, 0, $i), substr($content, $i)];
			$queries = iterator_to_array($parser->parseStringStream(new \ArrayIterator($chunks)));
			Assert::same($expected, $queries, "Failed with chunk boundary at offset $i");
		}
	}


	public function testParseFileThrowsOnNonExistentFile(): void
	{
		$parser = $this->createParser();
		Assert::exception(function () use ($parser) {
			$parser->parseFile(__DIR__ . '/../data/nonexistent.sql');
		}, RuntimeException::class);
	}


	/**
	 * @return list<string>
	 */
	protected static function randomChunks(string $s): array
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
}
