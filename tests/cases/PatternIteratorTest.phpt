<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\MultiQueryParser;

use ArrayIterator;
use Iterator;
use Nextras\MultiQueryParser\Exception\RuntimeException;
use Nextras\MultiQueryParser\PatternIterator;
use Tester\Assert;
use Tester\TestCase;


require_once __DIR__ . '/../bootstrap.php';


class PatternIteratorTest extends TestCase
{
	/**
	 * @return Iterator<string>
	 */
	private function stream(string ...$chunks): Iterator
	{
		return new ArrayIterator($chunks);
	}


	/**
	 * @return Iterator<string>
	 */
	private function generatorStream(string ...$chunks): Iterator
	{
		foreach ($chunks as $chunk) {
			yield $chunk;
		}
	}


	/**
	 * @return list<array<int|string, string>>
	 */
	private function collect(PatternIterator $iter): array
	{
		$results = [];
		foreach ($iter as $match) {
			$results[] = $match;
		}
		return $results;
	}


	// =====================================================================
	// Empty / trivial inputs
	// =====================================================================


	public function testEmptyStream(): void
	{
		$iter = new PatternIterator($this->stream(), '~\w+~A');
		Assert::same([], $this->collect($iter));
	}


	public function testSingleEmptyChunk(): void
	{
		$iter = new PatternIterator($this->stream(''), '~\w+~A');
		Assert::same([], $this->collect($iter));
	}


	public function testMultipleEmptyChunks(): void
	{
		$iter = new PatternIterator($this->stream('', '', ''), '~\w+~A');
		Assert::same([], $this->collect($iter));
	}


	// =====================================================================
	// Single chunk – basic matching
	// =====================================================================


	public function testSingleMatchInSingleChunk(): void
	{
		$iter = new PatternIterator($this->stream('hello'), '~\w+~A');
		$results = $this->collect($iter);
		Assert::count(1, $results);
		Assert::same('hello', $results[0][0]);
	}


	public function testMultipleMatchesInSingleChunk(): void
	{
		$iter = new PatternIterator($this->stream('aa;bb;cc'), '~[^;]+;?~A');
		$results = $this->collect($iter);
		Assert::count(3, $results);
		Assert::same('aa;', $results[0][0]);
		Assert::same('bb;', $results[1][0]);
		Assert::same('cc', $results[2][0]);
	}


	public function testSingleChunkWithTrailingDelimiter(): void
	{
		$iter = new PatternIterator($this->stream('aa;bb;'), '~[^;]+;~A');
		$results = $this->collect($iter);
		Assert::count(2, $results);
		Assert::same('aa;', $results[0][0]);
		Assert::same('bb;', $results[1][0]);
	}


	// =====================================================================
	// Single chunk – capture groups
	// =====================================================================


	public function testNamedCaptureGroups(): void
	{
		$iter = new PatternIterator(
			$this->stream('key=val'),
			'~(?<key>\w+)=(?<value>\w+)~A',
		);
		$results = $this->collect($iter);
		Assert::count(1, $results);
		Assert::same('key', $results[0]['key']);
		Assert::same('val', $results[0]['value']);
	}


	public function testNumericCaptureGroups(): void
	{
		$iter = new PatternIterator(
			$this->stream('2025-01-15'),
			'~(\d{4})-(\d{2})-(\d{2})~A',
		);
		$results = $this->collect($iter);
		Assert::count(1, $results);
		Assert::same('2025-01-15', $results[0][0]);
		Assert::same('2025', $results[0][1]);
		Assert::same('01', $results[0][2]);
		Assert::same('15', $results[0][3]);
	}


	// =====================================================================
	// Multi-chunk – match spanning chunks
	// =====================================================================


	public function testMatchSpanningTwoChunks(): void
	{
		$iter = new PatternIterator($this->stream('hel', 'lo'), '~\w+~A');
		$results = $this->collect($iter);
		Assert::count(1, $results);
		Assert::same('hello', $results[0][0]);
	}


	public function testMatchSpanningManyChunks(): void
	{
		$iter = new PatternIterator($this->stream('h', 'e', 'l', 'l', 'o'), '~\w+~A');
		$results = $this->collect($iter);
		Assert::count(1, $results);
		Assert::same('hello', $results[0][0]);
	}


	public function testMultipleMatchesAcrossChunks(): void
	{
		// "aa b" + "b cc" → should produce: "aa ", "bb ", "cc"
		$iter = new PatternIterator($this->stream('aa b', 'b cc'), '~\w+\s*~A');
		$results = $this->collect($iter);
		Assert::count(3, $results);
		Assert::same('aa ', $results[0][0]);
		Assert::same('bb ', $results[1][0]);
		Assert::same('cc', $results[2][0]);
	}


	public function testDelimiterSplitAcrossChunks(): void
	{
		// "SELECT 1" + ";" + " SELECT 2;"
		$iter = new PatternIterator(
			$this->stream('SELECT 1', ';', ' SELECT 2;'),
			'~\s*([^;]+);~A',
		);
		$results = $this->collect($iter);
		Assert::count(2, $results);
		Assert::same('SELECT 1', $results[0][1]);
		Assert::same('SELECT 2', $results[1][1]);
	}


	// =====================================================================
	// Boundary safety – match at end of chunk
	// =====================================================================


	public function testMatchAtEndOfChunkWaitsForMoreData(): void
	{
		// "abc" + "def": in the first chunk, "abc" matches \w+ but reaches end;
		// PatternIterator should wait for more data and yield "abcdef".
		$iter = new PatternIterator($this->stream('abc', 'def'), '~\w+~A');
		$results = $this->collect($iter);
		Assert::count(1, $results);
		Assert::same('abcdef', $results[0][0]);
	}


	public function testMatchAtEndOfChunkWithEmptyFollowingChunk(): void
	{
		$iter = new PatternIterator($this->stream('abc', ''), '~\w+~A');
		$results = $this->collect($iter);
		Assert::count(1, $results);
		Assert::same('abc', $results[0][0]);
	}


	public function testMatchInMiddleOfChunkFollowedByBoundaryMatch(): void
	{
		// "aa;bb" + "cc" → first match "aa;" does not reach end, yields.
		// Then "bb" reaches end, waits for "cc", then yields "bbcc".
		$iter = new PatternIterator($this->stream('aa;bb', 'cc'), '~[^;]+;?~A');
		$results = $this->collect($iter);
		Assert::count(2, $results);
		Assert::same('aa;', $results[0][0]);
		Assert::same('bbcc', $results[1][0]);
	}


	// =====================================================================
	// Generator-based stream (vs ArrayIterator)
	// =====================================================================


	public function testGeneratorStreamSingleChunk(): void
	{
		$iter = new PatternIterator($this->generatorStream('hello'), '~\w+~A');
		$results = $this->collect($iter);
		Assert::count(1, $results);
		Assert::same('hello', $results[0][0]);
	}


	public function testGeneratorStreamMultiChunk(): void
	{
		$iter = new PatternIterator($this->generatorStream('ab', 'cd'), '~\w+~A');
		$results = $this->collect($iter);
		Assert::count(1, $results);
		Assert::same('abcd', $results[0][0]);
	}


	public function testGeneratorStreamMultipleMatches(): void
	{
		$iter = new PatternIterator(
			$this->generatorStream('aa;bb;cc'),
			'~[^;]+;?~A',
		);
		$results = $this->collect($iter);
		Assert::count(3, $results);
		Assert::same('aa;', $results[0][0]);
		Assert::same('bb;', $results[1][0]);
		Assert::same('cc', $results[2][0]);
	}


	// =====================================================================
	// Very small chunks (single-byte)
	// =====================================================================


	public function testSingleByteChunks(): void
	{
		$input = 'ab;cd';
		$chunks = str_split($input, 1);
		$iter = new PatternIterator(new ArrayIterator($chunks), '~[^;]+;?~A');
		$results = $this->collect($iter);
		Assert::count(2, $results);
		Assert::same('ab;', $results[0][0]);
		Assert::same('cd', $results[1][0]);
	}


	public function testSingleByteChunksLongerInput(): void
	{
		$input = 'SELECT 1;SELECT 2;';
		$chunks = str_split($input, 1);
		$iter = new PatternIterator(new ArrayIterator($chunks), '~[^;]+;~A');
		$results = $this->collect($iter);
		Assert::count(2, $results);
		Assert::same('SELECT 1;', $results[0][0]);
		Assert::same('SELECT 2;', $results[1][0]);
	}


	// =====================================================================
	// Pattern mutation via setPattern()
	// =====================================================================


	public function testGetPattern(): void
	{
		$iter = new PatternIterator($this->stream(), '~test~A');
		Assert::same('~test~A', $iter->getPattern());
	}


	public function testSetPatternDuringIteration(): void
	{
		// Start with pattern matching "word;" then switch to "word//"
		$iter = new PatternIterator(
			$this->stream('aa;bb//cc//'),
			'~[^;]+;~A',
		);

		$results = [];
		foreach ($iter as $match) {
			$results[] = $match[0];
			if (count($results) === 1) {
				$iter->setPattern('~[^/]+//~A');
			}
		}

		Assert::same(['aa;', 'bb//', 'cc//'], $results);
	}


	public function testSetPatternChangesGetPattern(): void
	{
		$iter = new PatternIterator($this->stream(), '~old~A');
		$iter->setPattern('~new~A');
		Assert::same('~new~A', $iter->getPattern());
	}


	// =====================================================================
	// Error handling – unmatched content
	// =====================================================================


	public function testThrowsOnUnmatchedTrailingContent(): void
	{
		// Pattern matches digits only but input has trailing letters
		$iter = new PatternIterator($this->stream('123abc'), '~\d+~A');
		Assert::exception(function () use ($iter) {
			$this->collect($iter);
		}, RuntimeException::class);
	}


	public function testThrowsOnCompletelyUnmatchableContent(): void
	{
		$iter = new PatternIterator($this->stream('!!!'), '~\w+~A');
		Assert::exception(function () use ($iter) {
			$this->collect($iter);
		}, RuntimeException::class);
	}


	public function testThrowsOnUnmatchedContentAcrossChunks(): void
	{
		$iter = new PatternIterator($this->stream('aa;', '???'), '~[^;]+;~A');
		Assert::exception(function () use ($iter) {
			$this->collect($iter);
		}, RuntimeException::class);
	}


	// =====================================================================
	// SQL-like patterns (semicolon-delimited queries)
	// =====================================================================


	public function testSqlSemicolonDelimitedQueries(): void
	{
		$sql = "SELECT 1;\nSELECT 2;\nSELECT 3;";
		$pattern = '~\s*(?<query>[^;]+);~As';
		$iter = new PatternIterator($this->stream($sql), $pattern);
		$results = $this->collect($iter);
		Assert::count(3, $results);
		Assert::same('SELECT 1', $results[0]['query']);
		Assert::same('SELECT 2', $results[1]['query']);
		Assert::same('SELECT 3', $results[2]['query']);
	}


	public function testSqlQueriesAcrossSmallChunks(): void
	{
		$sql = "SELECT 1;\nSELECT 2;";
		$chunks = str_split($sql, 3);
		$pattern = '~\s*(?<query>[^;]+);~As';
		$iter = new PatternIterator(new ArrayIterator($chunks), $pattern);
		$results = $this->collect($iter);
		Assert::count(2, $results);
		Assert::same('SELECT 1', $results[0]['query']);
		Assert::same('SELECT 2', $results[1]['query']);
	}


	public function testSqlWithTrailingWhitespace(): void
	{
		$sql = "SELECT 1;\n  \n";
		$pattern = '~\s*(?:(?<query>[^;]+);|\z)~As';
		$iter = new PatternIterator($this->stream($sql), $pattern);
		$results = [];
		foreach ($iter as $match) {
			if (isset($match['query']) && $match['query'] !== '') {
				$results[] = $match['query'];
			} else {
				break; // \z match
			}
		}
		Assert::same(['SELECT 1'], $results);
	}


	// =====================================================================
	// Zero-length matches (e.g. \z)
	// =====================================================================


	public function testZeroLengthMatchIsNeverYielded(): void
	{
		// Pattern with \z alternative that matches empty string at end;
		// zero-length matches are not yielded to prevent infinite loops
		// and inconsistent results across chunk counts.
		$pattern = '~\s*(?:(?<query>[^;]+);|\z)~As';
		$iter = new PatternIterator($this->stream('SELECT 1;'), $pattern);
		$results = $this->collect($iter);
		Assert::count(1, $results);
		Assert::same('SELECT 1', $results[0]['query']);
	}


	public function testZeroLengthMatchOnEmptyInput(): void
	{
		$pattern = '~\s*(?:(?<query>[^;]+);|\z)~As';
		$iter = new PatternIterator($this->stream(''), $pattern);
		Assert::same([], $this->collect($iter));
	}


	public function testZeroLengthMatchAfterMultipleQueries(): void
	{
		$pattern = '~\s*(?:(?<query>[^;]+);|\z)~As';
		$iter = new PatternIterator($this->stream('a;b;c;'), $pattern);
		$results = $this->collect($iter);
		Assert::count(3, $results);
		Assert::same('a', $results[0]['query']);
		Assert::same('b', $results[1]['query']);
		Assert::same('c', $results[2]['query']);
	}


	public function testZeroLengthMatchWithTrailingWhitespace(): void
	{
		// Trailing whitespace is consumed by \s*, making the \z match non-zero-length;
		// that match IS yielded. The final \z-only match is zero-length and not yielded.
		$pattern = '~\s*(?:(?<query>[^;]+);|\z)~As';
		$iter = new PatternIterator($this->stream("a;\n  \n"), $pattern);
		$results = $this->collect($iter);
		Assert::count(2, $results);
		Assert::same('a', $results[0]['query']);
		Assert::same("\n  \n", $results[1][0]);
	}


	public function testZeroLengthMatchAcrossChunks(): void
	{
		$pattern = '~\s*(?:(?<query>[^;]+);|\z)~As';
		$iter = new PatternIterator($this->stream('a;', 'b;'), $pattern);
		$results = $this->collect($iter);
		Assert::count(2, $results);
		Assert::same('a', $results[0]['query']);
		Assert::same('b', $results[1]['query']);
	}


	// =====================================================================
	// Whitespace-only and special content
	// =====================================================================


	public function testWhitespaceOnlyContent(): void
	{
		$iter = new PatternIterator($this->stream('   '), '~\s+~A');
		$results = $this->collect($iter);
		Assert::count(1, $results);
		Assert::same('   ', $results[0][0]);
	}


	public function testMultilineContent(): void
	{
		$input = "line1\nline2\nline3\n";
		$iter = new PatternIterator($this->stream($input), '~[^\n]+\n~A');
		$results = $this->collect($iter);
		Assert::count(3, $results);
		Assert::same("line1\n", $results[0][0]);
		Assert::same("line2\n", $results[1][0]);
		Assert::same("line3\n", $results[2][0]);
	}


	// =====================================================================
	// Buffer trimming – large content across many chunks
	// =====================================================================


	public function testManyChunksAccumulateCorrectly(): void
	{
		$chunks = [];
		for ($i = 0; $i < 100; $i++) {
			$chunks[] = "q$i;";
		}
		$iter = new PatternIterator(new ArrayIterator($chunks), '~[^;]+;~A');
		$results = $this->collect($iter);
		Assert::count(100, $results);
		Assert::same('q0;', $results[0][0]);
		Assert::same('q99;', $results[99][0]);
	}
}


(new PatternIteratorTest())->run();
