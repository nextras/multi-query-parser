<?php declare(strict_types = 1);

namespace Nextras\MultiQueryParser;

use Iterator;
use Nextras\MultiQueryParser\Exception\RuntimeException;
use function file_get_contents;
use function preg_match;
use function preg_quote;
use function strlen;


class MySqlMultiQueryParser implements IMultiQueryParser
{
	public function parseFile(string $path): Iterator
	{
		$content = @file_get_contents($path);
		if ($content === false) {
			throw new RuntimeException("Cannot open file '$path'.");
		}

		$offset = 0;
		$pattern = $this->getQueryPattern(';');

		while (preg_match($pattern, $content, $match, 0, $offset) === 1) {
			$offset += strlen($match[0]);

			if (!empty($match['delimiter'])) {
				$pattern = $this->getQueryPattern($match['delimiter']);
			} elseif (!empty($match['query'])) {
				yield $match['query'];
			} else {
				break;
			}
		}

		if ($offset !== strlen($content)) {
			throw new RuntimeException("Failed to parse migration file '$path'");
		}
	}


	private function getQueryPattern(string $delimiter): string
	{
		$delimiterFirstBytePattern = preg_quote($delimiter[0], '~');
		$delimiterPattern = preg_quote($delimiter, '~');

		return /** @lang PhpRegExp */ "~
			(?:
					\\s
				|   /\\*                                                              (?: [^*]++ | \\*(?!/)        )*+ \\*/
				|   -- [^\\n]*+
			)*+

			(?:
				(?i:
					DELIMITER
					\\s++
					(?<delimiter>\\S++)
				)
				|
				(?:
					(?<query>
						(?:
								[^$delimiterFirstBytePattern'\"/$-]++
							|   '                                                     (?: \\\\.    | [^']            )*+ '
							|   \"                                                    (?: \\\\.    | [^\"]           )*+ \"
							|   /\\*                                                  (?: [^*]++   | \\*(?!/)        )*+ \\*/
							|   -- [^\\n]*+
							|   (?!$delimiterPattern) .
						)++
					)
					(?: $delimiterPattern | \\z )
				)
				|
				(?:
					\\z
				)
			)
		~xsAS";
	}
}
