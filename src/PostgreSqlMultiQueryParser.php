<?php declare(strict_types = 1);

namespace Nextras\MultiQueryParser;

use Iterator;


class PostgreSqlMultiQueryParser extends BaseMultiQueryParser
{
	protected function parseStringStreamToFragments(Iterator $stream): Iterator
	{
		$patternIterator = new PatternIterator($stream, $this->getQueryPattern());

		foreach ($patternIterator as $match) {
			yield from $this->buildFragments($match['leadingComments'] ?? null, $match['query'] ?? null);
		}
	}


	private function getQueryPattern(): string
	{
		// see https://www.postgresql.org/docs/9.6/static/sql-syntax-lexical.html
		// assumes standard_conforming_strings = on (default since PostgreSQL 9.1)

		return /** @lang PhpRegExp */ '~
			(?(DEFINE)
				(?<nestedBc> /\\* (?: [^/*]++ | /(?!\\*) | \\*(?!/) | (?&nestedBc) )*+ \\*/ )
			)

			\\s*+
			(?<leadingComments>
				(?:
						\\s
					|   /\\* (*PRUNE) (?: [^/*]++ | /(?!\\*) | \\*(?!/) | (?&nestedBc) )*+ \\*/
					|   -- [^\\n]*+
				)*+
			)

			(?:
				(?:
					(?<query>
						(?:
								(?:[^;\'"/$eE-]|[eE](?!\'))++
							|   \' (*PRUNE)                                         (?: [^\']                     )*+ \'
							|   [eE]\' (*PRUNE)                                     (?: \\\\.    | [^\']          )*+ \'
							|   " (*PRUNE)                                          (?: [^"]                      )*+ "
							|   /\\* (*PRUNE)                                       (?: [^/*]++ | /(?!\\*) | \\*(?!/) | (?&nestedBc) )*+ \\*/
							|   (\\$(?:[a-zA-Z_\\x80-\\xFF][\\w\\x80-\\xFF]*+)?\\$) (*PRUNE) (?: [^$]++   | (?!\\g{-1})\\$ )*+ \\g{-1}
							|   -- [^\\n]*+
							|   (?!;) .
						)*+
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
