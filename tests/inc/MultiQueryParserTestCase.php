<?php declare(strict_types = 1);

namespace Nextras\MultiQueryParser;

use LogicException;
use Nextras\MultiQueryParser\Exception\RuntimeException;
use Tester\Assert;
use Tester\TestCase;


abstract class MultiQueryParserTestCase extends TestCase
{
	abstract protected function createParser(): IMultiQueryParser;


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
