<?php declare(strict_types = 1);

namespace Nextras\MultiQueryParser;

use Iterator;
use function preg_quote;


class MySqlMultiQueryParser extends BaseMultiQueryParser
{
	public function parseStringStream(Iterator $stream): Iterator
	{
		$patternIterator = new PatternIterator($stream, $this->getQueryPattern(';'));

		foreach ($patternIterator as $match) {
			if (isset($match['delimiter']) && $match['delimiter'] !== '') {
				$patternIterator->setPattern($this->getQueryPattern($match['delimiter']));

			} elseif (isset($match['query']) && $match['query'] !== '') {
				yield $match['query'];
			}
		}
	}


	private function getQueryPattern(string $delimiter): string
	{
		$delimiterFirstBytePattern = preg_quote($delimiter[0], '~');
		$delimiterPattern = preg_quote($delimiter, '~');

		return /** @lang PhpRegExp */ "
		~
			(?:
					\\s
				|   /\\* (*PRUNE) (?: [^*]++ | \\*(?!/) )*+  \\*/
				|   --[^\\n]*+(?:\\n|\\z)
				|   \\#[^\\n]*+(?:\\n|\\z)
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
								[^$delimiterFirstBytePattern'\"\`/$#-]++
							|   ' (*PRUNE)                                            (?: \\\\.    | [^']            )*+ '
							|   \" (*PRUNE)                                           (?: \\\\.    | [^\"]           )*+ \"
							|   \` (*PRUNE)                                           (?: [^\`]++  | \`\`            )*+ \`
							|   /\\* (*PRUNE)                                         (?: [^*]++   | \\*(?!/)        )*+ \\*/
							|   --[^\\n]*+(?:\\n|\\z)
							|   \\#[^\\n]*+(?:\\n|\\z)
							|   (?!$delimiterPattern) .
						)*+
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
