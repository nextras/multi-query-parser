<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace Nextras\MultiQueryParser;

use Tester\Assert;


require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../inc/MultiQueryParserTestCase.php';


class SqliteMultiQueryParserTest extends MultiQueryParserTestCase
{
	protected function createParser(): IMultiQueryParser
	{
		return new SqliteMultiQueryParser();
	}


	protected function getDataFilePath(): string
	{
		return __DIR__ . '/../data/sqlite.sql';
	}


	protected function getExpectedFileQueryCount(): int
	{
		return 62;
	}


	public function testFile(): void
	{
		$parser = $this->createParser();
		$queries = iterator_to_array($parser->parseFile($this->getDataFilePath()));
		Assert::count($this->getExpectedFileQueryCount(), $queries);
		Assert::same("CREATE TRIGGER trigger_book_collections_update
	AFTER UPDATE ON book_collections
	FOR EACH ROW
BEGIN
	UPDATE book_collections SET updated_at = datetime('now') WHERE id = NEW.id;
END", $queries[19]);
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

			// Doubled single quotes
			["SELECT 'it''s';", ["SELECT 'it''s'"]],

			// Double-quoted identifiers protect semicolons
			['SELECT "col;name" FROM t;', ['SELECT "col;name" FROM t']],

			// Doubled double quotes inside identifiers
			['SELECT "col""name" FROM t;', ['SELECT "col""name" FROM t']],

			// Backtick identifiers protect semicolons
			['SELECT `col;name` FROM t;', ['SELECT `col;name` FROM t']],

			// Doubled backticks inside identifiers
			['SELECT `col``name` FROM t;', ['SELECT `col``name` FROM t']],

			// Bracket identifiers protect semicolons
			['SELECT [col;name] FROM t;', ['SELECT [col;name] FROM t']],
			['SELECT [a;b], [c;d] FROM t;', ['SELECT [a;b], [c;d] FROM t']],

			// Escaped brackets (doubled ]) inside bracket identifiers
			['SELECT [col]]name] FROM t;', ['SELECT [col]]name] FROM t']],

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

			// Non-nesting block comments (SQLite does NOT support nesting)
			["SELECT /* outer /* inner */ 1;", ["SELECT /* outer /* inner */ 1"]],

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

			// BEGIN...END preceded by other content (CREATE TRIGGER)
			[
				"CREATE TRIGGER t AFTER INSERT ON x FOR EACH ROW\nBEGIN\n\tSELECT 1;\nEND;",
				["CREATE TRIGGER t AFTER INSERT ON x FOR EACH ROW\nBEGIN\n\tSELECT 1;\nEND"],
			],

			// BEGIN TRANSACTION is a simple statement (not compound)
			["BEGIN TRANSACTION; SELECT 1;", ["BEGIN TRANSACTION", "SELECT 1"]],
			["BEGIN DEFERRED; SELECT 1;", ["BEGIN DEFERRED", "SELECT 1"]],
			["BEGIN IMMEDIATE; SELECT 1;", ["BEGIN IMMEDIATE", "SELECT 1"]],
			["BEGIN EXCLUSIVE; SELECT 1;", ["BEGIN EXCLUSIVE", "SELECT 1"]],

			// Bare BEGIN; is a simple statement (transaction)
			["BEGIN; SELECT 1;", ["BEGIN", "SELECT 1"]],

			// Mixed identifier styles in one query
			['SELECT [a], "b", `c` FROM t;', ['SELECT [a], "b", `c` FROM t']],
		];
	}


	/**
	 * @return list<array{list<string>, list<string>}>
	 */
	protected function provideChunkBoundaryData(): array
	{
		return [
			// Single-quoted string spanning chunks
			[
				["SELECT 'a;b", "c';"],
				["SELECT 'a;bc'"],
			],
			// Double-quoted identifier spanning chunks
			[
				['SELECT "a;b', 'c";'],
				['SELECT "a;bc"'],
			],
			// Backtick identifier spanning chunks
			[
				['SELECT `a;b', 'c`;'],
				['SELECT `a;bc`'],
			],
			// Bracket identifier spanning chunks
			[
				["SELECT [col;na", "me] FROM t;"],
				["SELECT [col;name] FROM t"],
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
		];
	}
}


(new SqliteMultiQueryParserTest())->run();
