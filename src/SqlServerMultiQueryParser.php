<?php declare(strict_types = 1);

namespace Nextras\MultiQueryParser;

use Iterator;


class SqlServerMultiQueryParser extends BaseMultiQueryParser
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
		$simpleQuery = /** @lang PhpRegExp */ '~
			(?:
					\\s
				|   /\\* (*PRUNE) (?: [^/*]++ | /(?!\\*) | \\*(?!/) | (?&nestedBc) )*+ \\*/
				|   -- [^\\n]*+
			)*+
			(?<simplequery>
				(?:
						[^;\'"[/-]++
					|   \' (*PRUNE)                                         (?: [^\']                     )*+ \'
					|   " (*PRUNE)                                          (?: [^"]                      )*+ "
					|   \\[ (*PRUNE)                                        [^\\]]*+ (?: \\]\\] [^\\]]*+ )* \\]
					|   /\\* (*PRUNE)                                       (?: [^/*]++ | /(?!\\*) | \\*(?!/) | (?&nestedBc) )*+ \\*/
					|   -- [^\\n]*+
					|   (?!;) .
				)++
			)
			;
		~x';
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
							 	[^B;\'"[/-]++
							|   \' (*PRUNE)                                         (?: [^\']                     )*+ \'
							|   " (*PRUNE)                                          (?: [^"]                      )*+ "
							|   \\[ (*PRUNE)                                        [^\\]]*+ (?: \\]\\] [^\\]]*+ )* \\]
							|   /\\* (*PRUNE)                                       (?: [^/*]++ | /(?!\\*) | \\*(?!/) | (?&nestedBc) )*+ \\*/
							|   BEGIN (?: \s*END\s*| ' . substr($simpleQuery, 1, -2) . ')*
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
