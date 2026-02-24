<?php declare(strict_types = 1);

namespace Nextras\MultiQueryParser;

use Iterator;


class SqliteMultiQueryParser extends BaseMultiQueryParser
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
					\s
				|   /\* (*PRUNE) (?: [^*]++ | \*(?!/) )*+ \*/
				|   -- [^\n]*+
			)*+
			(?<simplequery>
				(?:
						[^;\'"`[/-]++
					|   \' (*PRUNE)                                         (?: \'\' | [^\'] )*+ \'
					|   " (*PRUNE)                                          (?: "" | [^"] )*+ "
					|   ` (*PRUNE)                                          (?: `` | [^`] )*+ `
					|   \[ (*PRUNE)                                         [^\]]*+ (?: \]\] [^\]]*+ )* \]
					|   /\* (*PRUNE)                                        (?: [^*]++ | \*(?!/) )*+ \*/
					|   -- [^\n]*+
					|   (?!;) .
				)++
			)
			;
		~x';
		return /** @lang PhpRegExp */ '~
			(?:
					\s
				|   /\* (*PRUNE) (?: [^*]++ | \*(?!/) )*+ \*/
				|   -- [^\n]*+
			)*+

			(?:
				(?:
					(?<query>
						(?:
							 	[^bB;\'"`[/-]++
							|   \' (*PRUNE)                                         (?: \'\' | [^\'] )*+ \'
							|   " (*PRUNE)                                          (?: "" | [^"] )*+ "
							|   ` (*PRUNE)                                          (?: `` | [^`] )*+ `
							|   \[ (*PRUNE)                                         [^\]]*+ (?: \]\] [^\]]*+ )* \]
							|   /\* (*PRUNE)                                        (?: [^*]++ | \*(?!/) )*+ \*/
							|   (?i:BEGIN) (?!\s*(?:(?i:TRANSACTION|DEFERRED|IMMEDIATE|EXCLUSIVE)\b|;|\z)) (*PRUNE) (?: (?i:\s*END)\s*| ' . substr($simpleQuery, 1, -2) . ')*
							|   -- [^\n]*+
							|   (?!;) .
						)*+
					)
					(?: ; | \z )
				)
				|
				(?:
					\z
				)
			)
		~xsAS';
	}
}
