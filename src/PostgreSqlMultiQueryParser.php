<?php declare(strict_types = 1);

namespace Nextras\MultiQueryParser;

use Iterator;


class PostgreSqlMultiQueryParser implements IMultiQueryParser
{
	use BufferedFileParseTrait;


	public function parseFile(string $path): Iterator
	{
		return $this->parseFileBuffered(
			$path,
			$this->getQueryPattern(),
			static function (array $match): array {
				$query = (isset($match['query']) && $match['query'] !== '') ? $match['query'] : null;
				return [$query, null];
			}
		);
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
