<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace Nextras\MultiQueryParser;

use Tester\Assert;


require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../inc/MultiQueryParserTestCase.php';


class PostgreSqlMultiQueryParserTest extends MultiQueryParserTestCase
{
	protected function createParser(): IMultiQueryParser
	{
		return new PostgreSqlMultiQueryParser();
	}


	protected function getDataFilePath(): string
	{
		return __DIR__ . '/../data/postgres.sql';
	}


	protected function getExpectedFileQueryCount(): int
	{
		return 67;
	}


	public function testFile(): void
	{
		$parser = $this->createParser();
		$queries = iterator_to_array($parser->parseFile($this->getDataFilePath()));
		Assert::count($this->getExpectedFileQueryCount(), $queries);
		Assert::same("CREATE FUNCTION \"book_collections_before\"() RETURNS TRIGGER AS
\$BODY$
BEGIN
    NEW.\"updated_at\" = NOW();
    return NEW;
END;
\$BODY$
    LANGUAGE 'plpgsql' VOLATILE", $queries[16]);
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

			// Nested block comments (PostgreSQL supports nesting)
			["SELECT /* outer /* inner */ still comment */ 1;", ["SELECT /* outer /* inner */ still comment */ 1"]],
			["/* /* nested */ */ SELECT 1;", ["SELECT 1"]],
			["SELECT /* a /* b /* c */ c */ a */ 1;", ["SELECT /* a /* b /* c */ c */ a */ 1"]],
		];
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
			// Nested block comment spanning chunks
			[
				["SELECT /* outer /* ;inner", " */ still; */ 1;"],
				["SELECT /* outer /* ;inner */ still; */ 1"],
			],
		];
	}
}


(new PostgreSqlMultiQueryParserTest())->run();
