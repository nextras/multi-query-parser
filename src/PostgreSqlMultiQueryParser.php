<?php declare(strict_types = 1);

namespace Nextras\MultiQueryParser;

use Iterator;


class PostgreSqlMultiQueryParser extends BaseMultiQueryParser
{
	public function parseStringStream(Iterator $stream): Iterator
	{
		$patternIterator = new PatternIterator($stream, $this->getQueryPattern());

		foreach ($patternIterator as $match) {
			if (isset($match['query']) && $match['query'] !== '') {
				yield $match['query'];
			}
		}
	}


	private function getQueryPattern(): string
	{
		// see https://www.postgresql.org/docs/9.6/static/sql-syntax-lexical.html
		// assumes standard_conforming_strings = on (default since PostgreSQL 9.1)

		return /** @lang PhpRegExp */ '~
			(?:
					\\s
				|   /\\*                                                            (?: [^*]++   | \\*(?!/)       )*+ (?:\\*/|\\z)
				|   -- [^\\n]*+
			)*+

			(?:
				(?:
					(?<query>
						(?:
								(?:[^;\'"/$eE-]|[eE](?!\'))++
							|   \'                                                  (?: [^\']                     )*+ (?:\'|\\z)
							|   [eE]\'                                              (?: \\\\.    | [^\']          )*+ (?:\'|\\z)
							|   "                                                   (?: [^"]                      )*+ (?:"|\\z)
							|   /\\*                                                (?: [^*]++   | \\*(?!/)       )*+ (?:\\*/|\\z)
							|   (\\$(?:[a-zA-Z_\\x80-\\xFF][\\w\\x80-\\xFF]*+)?\\$) (?: [^$]++   | (?!\\g{-1})\\$ )*+ (?: \\g{-1} | \\z )
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
