<?php declare(strict_types = 1);

namespace Nextras\MultiQueryParser;

use Iterator;


class SqlServerMultiQueryParser extends BaseMultiQueryParser
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
		$simpleQuery = /** @lang PhpRegExp */ '~
			(?:
					\\s
				|   /\\* (?: [^*]++   | \\*(?!/) )*+ \\*/
				|   -- [^\\n]*+
			)*+
			(?<simplequery>
				(?:
						[^;\'"[/-]++
					|   \'                                                  (?: [^\']                     )*+ \'
					|   "                                                   (?: [^"]                      )*+ "
					|   /\\*                                                (?: [^*]++   | \\*(?!/)       )*+ \\*/
					|   -- [^\\n]*+
					|   (?!;) .
				)++
			)
			;
		~x';
		return /** @lang PhpRegExp */ '~
			(?:
					\\s
				|   /\\* (?: [^*]++   | \\*(?!/) )*+ \\*/
				|   -- [^\\n]*+
			)*+

			(?:
				(?:
					(?<query>
						(?:
							 	[^B;\'"[/-]++
							|   \'                                                  (?: [^\']                     )*+ \'
							|   "                                                   (?: [^"]                      )*+ "
							|   /\\*                                                (?: [^*]++   | \\*(?!/)       )*+ \\*/
							|   BEGIN (?: \s*END\s*| ' . substr($simpleQuery, 1, -2) . ')*
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
