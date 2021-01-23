<?php declare(strict_types = 1);

namespace Nextras\MultiQueryParser;

use Iterator;
use Nextras\MultiQueryParser\Exception\RuntimeException;
use function file_get_contents;
use function preg_match;
use function strlen;


class PgSqlMultiQueryParser implements IMultiQueryParser
{
	public function parseFile(string $path): Iterator
	{
		$content = @file_get_contents($path);
		if ($content === false) {
			throw new RuntimeException("Cannot open file '$path'.");
		}

		$offset = 0;
		$pattern = $this->getQueryPattern();

		while (preg_match($pattern, $content, $match, 0, $offset)) {
			$offset += strlen($match[0]);

			if (!empty($match['query'])) {
				yield $match['query'];
			} else {
				break;
			}
		}

		if ($offset !== strlen($content)) {
			throw new RuntimeException("Failed to parse migration file '$path'");
		}
	}


	private function getQueryPattern(): string
	{
		// see https://www.postgresql.org/docs/9.6/static/sql-syntax-lexical.html
		// assumes standard_conforming_strings = on (default since PostgreSQL 9.1)

		return /** @lang PhpRegExp */ '~
			(?:
					\\s
				|   /\\*                                                            (?: [^*]++   | \\*(?!/)       )*+ \\*/
				|   -- [^\\n]*+
			)*+

			(?:
				(?:
					(?<query>
						(?:
								[^;\'"/$-]++
							|   \'                                                  (?: [^\']                     )*+ \'
							|   [eE]\'                                              (?: \\\\.    | [^\']          )*+ \'
							|   "                                                   (?: [^"]                      )*+ "
							|   /\\*                                                (?: [^*]++   | \\*(?!/)       )*+ \\*/
							|   (\\$(?:[a-zA-Z_\\x80-\\xFF][\\w\\x80-\\xFF]*+)?\\$) (?: [^$]++   | (?!\\g{-1})\\$ )*+ \\g{-1}
							|   -- [^\\n]*+
							|   (?!;) .
						)++
					)
					(?: ; | \\z )
				)
				|
				(?:
					\\z
				)
			)
		~xsAS';
	}
}
