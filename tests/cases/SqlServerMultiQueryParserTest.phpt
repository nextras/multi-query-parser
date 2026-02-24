<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace Nextras\MultiQueryParser;

use Tester\Assert;


require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../inc/MultiQueryParserTestCase.php';


class SqlServerMultiQueryParserTest extends MultiQueryParserTestCase
{
	protected function createParser(): IMultiQueryParser
	{
		return new SqlServerMultiQueryParser();
	}


	protected function getDataFilePath(): string
	{
		return __DIR__ . '/../data/sqlserver.sql';
	}


	protected function getExpectedFileQueryCount(): int
	{
		return 71;
	}


	public function testFile(): void
	{
		$parser = $this->createParser();
		$queries = iterator_to_array($parser->parseFile($this->getDataFilePath()));
		Assert::count($this->getExpectedFileQueryCount(), $queries);
		Assert::same("CREATE TRIGGER mydatabase.trigger_book_stats
	ON yourtable.books
	AFTER INSERT, DELETE
	AS
BEGIN
	SET NOCOUNT ON;
	INSERT INTO yourtable.book_stats(
		book_id,
		string_value
	)
	SELECT
		i.book_id,
		'INS'
	FROM
		inserted i
	UNION ALL
	SELECT
		d.book_id,
		'DEL'
	FROM
		deleted d;
END", $queries[67]);
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

			// Single-quoted strings protect semicolons
			["SELECT 'a;b';", ["SELECT 'a;b'"]],
			["SELECT ';;;';", ["SELECT ';;;'"]],
			["SELECT '';", ["SELECT ''"]],

			// Double-quoted identifiers protect semicolons
			['SELECT "col;name" FROM t;', ['SELECT "col;name" FROM t']],

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

			// BEGIN...END block with internal semicolons (treated as single query)
			[
				"BEGIN\n\tSELECT 1;\n\tSELECT 2;\nEND;",
				["BEGIN\n\tSELECT 1;\n\tSELECT 2;\nEND"],
			],

			// BEGIN...END with only END (no internal queries)
			["BEGIN END;", ["BEGIN END"]],
			["BEGIN\nEND;", ["BEGIN\nEND"]],

			// BEGIN keyword inside a string literal (should not trigger BEGIN...END)
			["SELECT 'BEGIN';", ["SELECT 'BEGIN'"]],
			['SELECT "BEGIN";', ['SELECT "BEGIN"']],

			// Multiple BEGIN...END blocks as separate queries
			[
				"BEGIN\n\tSELECT 1;\nEND;\nBEGIN\n\tSELECT 2;\nEND;",
				["BEGIN\n\tSELECT 1;\nEND", "BEGIN\n\tSELECT 2;\nEND"],
			],

			// BEGIN...END with string containing semicolons
			[
				"BEGIN\n\tSELECT 'a;b';\nEND;",
				["BEGIN\n\tSELECT 'a;b';\nEND"],
			],

			// BEGIN...END preceded by other content
			[
				"CREATE TRIGGER t AS\nBEGIN\n\tSELECT 1;\nEND;",
				["CREATE TRIGGER t AS\nBEGIN\n\tSELECT 1;\nEND"],
			],

			// Bracket-quoted identifiers (basic case without semicolons)
			['SELECT [column] FROM [table];', ['SELECT [column] FROM [table]']],

			// Bracket-quoted identifiers protect semicolons
			['SELECT [col;name] FROM t;', ['SELECT [col;name] FROM t']],
			['SELECT [a;b], [c;d] FROM t;', ['SELECT [a;b], [c;d] FROM t']],

			// Escaped brackets (doubled ]) inside bracket identifiers
			['SELECT [col]]name] FROM t;', ['SELECT [col]]name] FROM t']],

			// Nested block comments (SQL Server supports nesting)
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
			// Double-quoted string spanning chunks
			[
				['SELECT "a;b', 'c";'],
				['SELECT "a;bc"'],
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
			// BEGIN...END with string spanning chunks
			[
				["BEGIN\n\tSELECT 'a;b", "c';\nEND;"],
				["BEGIN\n\tSELECT 'a;bc';\nEND"],
			],
			// Bracket identifier spanning chunks
			[
				["SELECT [col;na", "me] FROM t;"],
				["SELECT [col;name] FROM t"],
			],
			// Nested block comment spanning chunks
			[
				["SELECT /* outer /* ;inner", " */ still; */ 1;"],
				["SELECT /* outer /* ;inner */ still; */ 1"],
			],
		];
	}
}


(new SqlServerMultiQueryParserTest())->run();
