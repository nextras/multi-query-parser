<?php declare(strict_types = 1);

namespace Nextras\MultiQueryParser;

use Iterator;
use Nextras\MultiQueryParser\Exception\RuntimeException;
use function file_get_contents;
use function preg_match;
use function strlen;


class SqlServerMultiQueryParser implements IMultiQueryParser
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

			if (isset($match['query']) && $match['query'] !== '') {
				yield $match['query'];
			} else {
				break;
			}
		}

		if ($offset !== strlen($content)) {
			throw new RuntimeException("Failed to parse file '$path', please report an issue.");
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
