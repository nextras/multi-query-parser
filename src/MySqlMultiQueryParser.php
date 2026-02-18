<?php declare(strict_types = 1);

namespace Nextras\MultiQueryParser;

use Iterator;
use function preg_quote;


class MySqlMultiQueryParser implements IMultiQueryParser
{
	use BufferedFileParseTrait;


	public function parseFile(string $path): Iterator
	{
		return $this->parseFileBuffered(
			$path,
			$this->getQueryPattern(';'),
			function (array $match): array {
				if (isset($match['delimiter']) && $match['delimiter'] !== '') {
					return [null, $this->getQueryPattern($match['delimiter'])];
				}
				$query = (isset($match['query']) && $match['query'] !== '') ? $match['query'] : null;
				return [$query, null];
			}
		);
	}


	private function getQueryPattern(string $delimiter): string
	{
		$delimiterFirstBytePattern = preg_quote($delimiter[0], '~');
		$delimiterPattern = preg_quote($delimiter, '~');

		return /** @lang PhpRegExp */ "
		~
			(?:
					\\s
				|   /\\*  (?: [^*]++ | \\*(?!/) )*+  \\*/
				|   --[^\\n]*+(?:\\n|\\z)
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
							|   --[^\\n]*+(?:\\n|\\z)
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
