<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace Nextras\MultiQueryParser;

use Tester\Assert;


require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../inc/MultiQueryParserTestCase.php';


class MySqlMultiQueryParserTest extends MultiQueryParserTestCase
{
	protected function createParser(): IMultiQueryParser
	{
		return new MySqlMultiQueryParser();
	}


	protected function getDataFilePath(): string
	{
		return __DIR__ . '/../data/mysql.sql';
	}


	protected function getExpectedFileQueryCount(): int
	{
		return 61;
	}


	/**
	 * @dataProvider provideDelimitersData
	 * @param list<string> $expectedQueries
	 */
	public function testDelimiter(string $content, array $expectedQueries): void
	{
		$parser = $this->createParser();
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
			// DELIMITER $$ with $mle$ dollar-quoted body
			[
				implode("\n", [
					'SELECT 1;',
					'DELIMITER $$',
					'CREATE FUNCTION gcd(a INT, b INT) RETURNS INT NO SQL LANGUAGE JAVASCRIPT AS $mle$ let x = a; let y = b; $mle$$$',
					'DELIMITER ;',
					'SELECT 2;',
				]),
				[
					'SELECT 1',
					'CREATE FUNCTION gcd(a INT, b INT) RETURNS INT NO SQL LANGUAGE JAVASCRIPT AS $mle$ let x = a; let y = b; $mle$',
					'SELECT 2',
				],
			],
		];
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

			// Semicolons inside string literals are not delimiters
			["SELECT 'a;b';", ["SELECT 'a;b'"]],
			['SELECT "a;b";', ['SELECT "a;b"']],
			["SELECT 'a;b', 'c;d';", ["SELECT 'a;b', 'c;d'"]],
			["SELECT ';;;';", ["SELECT ';;;'"]],
			["SELECT '';", ["SELECT ''"]],

			// Backslash escaping in strings
			["SELECT 'it\\'s';", ["SELECT 'it\\'s'"]],
			['SELECT "col\\"name";', ['SELECT "col\\"name"']],
			["SELECT '\\;';", ["SELECT '\\;'"]],

			// Semicolons inside comments are not delimiters
			["SELECT /* ; */ 1;", ["SELECT /* ; */ 1"]],
			["SELECT /* ; ; ; */ 1;", ["SELECT /* ; ; ; */ 1"]],

			// Line comment between queries (semicolon before comment)
			["SELECT 1; -- has ; in comment\nSELECT 2;", ["SELECT 1", "SELECT 2"]],

			// Line comment inside a query captures everything until next real delimiter
			["SELECT 1 -- comment with ;\nSELECT 2;", ["SELECT 1 -- comment with ;\nSELECT 2"]],

			// Queries without trailing semicolon (terminated by end of input)
			["SELECT 1", ["SELECT 1"]],
			["SELECT 1; SELECT 2", ["SELECT 1", "SELECT 2"]],

			// Forward slash and dash not starting comments
			["SELECT 5/3;", ["SELECT 5/3"]],
			["SELECT 5-3;", ["SELECT 5-3"]],
			["SELECT 1 / 2 + 3 - 4;", ["SELECT 1 / 2 + 3 - 4"]],

			// Comment positioning
			["/* prefix */ SELECT 1;", ["SELECT 1"]],
			["-- prefix\nSELECT 1;", ["SELECT 1"]],
			["SELECT 1; /* between */ SELECT 2;", ["SELECT 1", "SELECT 2"]],
			["SELECT 1; -- between\nSELECT 2;", ["SELECT 1", "SELECT 2"]],

			// Only comments (no queries)
			["/* only a comment */", []],
			["-- only a comment", []],
			["-- line 1\n-- line 2\n", []],
			["/* c1 */ /* c2 */", []],

			// Block comment edge cases
			["SELECT /* contains * star */ 1;", ["SELECT /* contains * star */ 1"]],
			["SELECT /* contains /* slash-star */ 1;", ["SELECT /* contains /* slash-star */ 1"]],

			// DELIMITER is case-insensitive
			[
				"delimiter //\nSELECT 1//\nDELIMITER ;",
				["SELECT 1"],
			],
			[
				"Delimiter //\nSELECT 1//\nDELIMITER ;",
				["SELECT 1"],
			],

			// CRLF line endings
			["SELECT 1;\r\nSELECT 2;\r\n", ["SELECT 1", "SELECT 2"]],

			// Consecutive string literals
			["SELECT 'a' 'b';", ["SELECT 'a' 'b'"]],

			// Whitespace variations
			["SELECT\t1;", ["SELECT\t1"]],
			["\n\nSELECT 1;\n\n", ["SELECT 1"]],

			// Hash comments (#) — MySQL-specific line comments
			["# this is a comment\nSELECT 1;", ["SELECT 1"]],
			["SELECT 1; # comment\nSELECT 2;", ["SELECT 1", "SELECT 2"]],
			["SELECT 1 # comment with ;\nSELECT 2;", ["SELECT 1 # comment with ;\nSELECT 2"]],
			["# only a comment", []],
			["# comment 1\n# comment 2\n", []],

			// Backtick-quoted identifiers protect semicolons
			["SELECT `col;name` FROM t;", ["SELECT `col;name` FROM t"]],
			["SELECT `a` FROM t;", ["SELECT `a` FROM t"]],

			// Escaped backticks (doubled) inside backtick identifiers
			["SELECT `col``name` FROM t;", ["SELECT `col``name` FROM t"]],

			// Dollar-quoted strings in JavaScript stored programs
			[
				'CREATE FUNCTION gcd(a INT, b INT) RETURNS INT NO SQL LANGUAGE JAVASCRIPT AS $mle$ let x = a; let y = b; $mle$;',
				['CREATE FUNCTION gcd(a INT, b INT) RETURNS INT NO SQL LANGUAGE JAVASCRIPT AS $mle$ let x = a; let y = b; $mle$'],
			],
			[
				'CREATE FUNCTION js_add(a INT, b INT) RETURNS INT LANGUAGE JAVASCRIPT AS $$ return a + b; $$;',
				['CREATE FUNCTION js_add(a INT, b INT) RETURNS INT LANGUAGE JAVASCRIPT AS $$ return a + b; $$'],
			],
			// Nested dollar-quoted strings with different tags
			[
				'CREATE FUNCTION nested() RETURNS INT LANGUAGE JAVASCRIPT AS $mle$ let s = $inner$;$inner$; return 1; $mle$;',
				['CREATE FUNCTION nested() RETURNS INT LANGUAGE JAVASCRIPT AS $mle$ let s = $inner$;$inner$; return 1; $mle$'],
			],
		];
	}


	/**
	 * @return list<array{list<string>, list<string>}>
	 */
	protected function provideChunkBoundaryData(): array
	{
		// The bug triggers when a chunk boundary falls inside a string/comment
		// that contains a semicolon, AND there is content after the semicolon
		// still within the same chunk. The `;` falsely acts as a delimiter and
		// the remaining content prevents the match from reaching end-of-buffer
		// (which would trigger PatternIterator's safety mechanism).
		return [
			// Single-quoted string: chunk has content after the false `;`
			[
				["SELECT 'a;b", "c';"],
				["SELECT 'a;bc'"],
			],
			// Double-quoted string: same issue
			[
				['SELECT "a;b', 'c";'],
				['SELECT "a;bc"'],
			],
			// Block comment: chunk has content after the false `;`
			[
				["SELECT /* a;b", "c */ 1;"],
				["SELECT /* a;bc */ 1"],
			],
			// Multiple queries — string spans chunk, content after `;`
			[
				["SELECT 'x;y", "z'; SELECT 2;"],
				["SELECT 'x;yz'", "SELECT 2"],
			],
			// Block comment in leading whitespace spanning chunks
			[
				["/* x;y", "z */ SELECT 1;"],
				["SELECT 1"],
			],
			// String with backslash escaping spanning chunks
			[
				["SELECT 'a;b\\'", "c';"],
				["SELECT 'a;b\\'c'"],
			],
			// Hash comment spanning chunks
			[
				["SELECT 1; # comment;wi", "th semi\nSELECT 2;"],
				["SELECT 1", "SELECT 2"],
			],
			// Backtick identifier spanning chunks
			[
				["SELECT `col;na", "me` FROM t;"],
				["SELECT `col;name` FROM t"],
			],
			// Dollar-quoted string spanning chunks
			[
				['CREATE FUNCTION f() RETURNS INT LANGUAGE JAVASCRIPT AS $mle$ let x = 1;', ' return x; $mle$;'],
				['CREATE FUNCTION f() RETURNS INT LANGUAGE JAVASCRIPT AS $mle$ let x = 1; return x; $mle$'],
			],
		];
	}
}


(new MySqlMultiQueryParserTest())->run();
