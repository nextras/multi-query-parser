<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace Nextras\MultiQueryParser;

use LogicException;
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


	/**
	 * @dataProvider provideEdgeCasesData
	 * @param list<string> $expectedQueries
	 */
	public function testEdgeCases(string $content, array $expectedQueries): void
	{
		$parser = new PostgreSqlMultiQueryParser();
		$queries = iterator_to_array($parser->parseString($content));
		Assert::same($expectedQueries, $queries);
	}


	/**
	 * @return list<array{string, list<string>}>
	 */
	protected function provideEdgeCasesData(): array
	{
		return [
			// Empty / whitespace-only input
			['', []],
			["  \n\t\n  ", []],

			// Standard single-quoted strings protect semicolons
			["SELECT 'a;b';", ["SELECT 'a;b'"]],
			["SELECT ';;;';", ["SELECT ';;;'"]],
			["SELECT '';", ["SELECT ''"]],

			// Doubled single quotes (standard_conforming_strings = on)
			["SELECT 'it''s';", ["SELECT 'it''s'"]],

			// Double-quoted identifiers protect semicolons
			['SELECT "col;name" FROM t;', ['SELECT "col;name" FROM t']],

			// E-strings with backslash escaping
			["SELECT E'it\\'s';", ["SELECT E'it\\'s'"]],
			["SELECT e'it\\'s';", ["SELECT e'it\\'s'"]],
			["SELECT E'semi\\;colon';", ["SELECT E'semi\\;colon'"]],

			// Dollar-quoted strings protect semicolons
			['SELECT $$hello;world$$;', ['SELECT $$hello;world$$']],
			['SELECT $$multiple;semi;colons$$;', ['SELECT $$multiple;semi;colons$$']],

			// Dollar-quoted strings with tags
			['SELECT $tag$hello;world$tag$;', ['SELECT $tag$hello;world$tag$']],
			['SELECT $fn_body$SELECT 1; SELECT 2;$fn_body$;', ['SELECT $fn_body$SELECT 1; SELECT 2;$fn_body$']],

			// Empty dollar-quoted string
			['SELECT $$$$;', ['SELECT $$$$']],
			['SELECT $x$$x$;', ['SELECT $x$$x$']],

			// Dollar sign as positional parameter (not a dollar-quote)
			['SELECT $1;', ['SELECT $1']],
			['SELECT $1, $2;', ['SELECT $1, $2']],

			// Nested dollar-quoted strings with different tags
			['SELECT $a$contains $b$;$b$ end$a$;', ['SELECT $a$contains $b$;$b$ end$a$']],

			// Semicolons inside comments are not delimiters
			["SELECT /* ; */ 1;", ["SELECT /* ; */ 1"]],
			["SELECT 1; -- has ; in comment\nSELECT 2;", ["SELECT 1", "SELECT 2"]],

			// Line comment inside a query
			["SELECT 1 -- comment with ;\nSELECT 2;", ["SELECT 1 -- comment with ;\nSELECT 2"]],

			// Queries without trailing semicolon
			["SELECT 1", ["SELECT 1"]],
			["SELECT 1; SELECT 2", ["SELECT 1", "SELECT 2"]],

			// Forward slash and dash not starting comments
			["SELECT 5/3;", ["SELECT 5/3"]],
			["SELECT 5-3;", ["SELECT 5-3"]],

			// Only comments
			["/* only a comment */", []],
			["-- only a comment", []],

			// Comment positioning
			["/* prefix */ SELECT 1;", ["SELECT 1"]],
			["-- prefix\nSELECT 1;", ["SELECT 1"]],
			["SELECT 1; /* between */ SELECT 2;", ["SELECT 1", "SELECT 2"]],

			// Block comment edge cases
			["SELECT /* contains * star */ 1;", ["SELECT /* contains * star */ 1"]],

			// CRLF line endings
			["SELECT 1;\r\nSELECT 2;\r\n", ["SELECT 1", "SELECT 2"]],
		];
	}


	/**
	 * @dataProvider provideChunkBoundaryData
	 * @param list<string> $chunks
	 * @param list<string> $expectedQueries
	 */
	public function testChunkBoundary(array $chunks, array $expectedQueries): void
	{
		$parser = new PostgreSqlMultiQueryParser();
		$queries = iterator_to_array($parser->parseStringStream(new \ArrayIterator($chunks)));
		Assert::same($expectedQueries, $queries);
	}


	/**
	 * @return list<array{list<string>, list<string>}>
	 */
	protected function provideChunkBoundaryData(): array
	{
		return [
			// Single-quoted string spanning chunks with content after `;`
			[
				["SELECT 'a;b", "c';"],
				["SELECT 'a;bc'"],
			],
			// Double-quoted identifier spanning chunks
			[
				['SELECT "a;b', 'c";'],
				['SELECT "a;bc"'],
			],
			// E-string spanning chunks
			[
				["SELECT E'a;b", "c';"],
				["SELECT E'a;bc'"],
			],
			// Block comment spanning chunks
			[
				["SELECT /* a;b", "c */ 1;"],
				["SELECT /* a;bc */ 1"],
			],
			// Block comment in leading whitespace spanning chunks
			[
				["/* x;y", "z */ SELECT 1;"],
				["SELECT 1"],
			],
			// Dollar-quoted string spanning chunks (already fixed)
			[
				['SELECT $$a;b', 'c$$;'],
				['SELECT $$a;bc$$'],
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


	public function testFileWithRandomizedChunking(): void
	{
		$content = file_get_contents(__DIR__ . '/data/postgres.sql');

		if ($content === false) {
			throw new LogicException('Failed to read file content');
		}

		$parser = new PostgreSqlMultiQueryParser();
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
}


(new PostgreSqlMultiQueryParserTest())->run();
