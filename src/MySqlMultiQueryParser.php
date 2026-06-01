<?php declare(strict_types = 1);

namespace Nextras\MultiQueryParser;

use Iterator;
use function preg_quote;


class MySqlMultiQueryParser extends BaseMultiQueryParser
{
	protected function parseStringStreamToFragments(Iterator $stream): Iterator
	{
		$patternIterator = new PatternIterator($stream, $this->getQueryPattern(';'));

		foreach ($patternIterator as $match) {
			yield from $this->buildFragments($match['leadingComments'] ?? null, $match['query'] ?? null);

			if (isset($match['delimiter']) && $match['delimiter'] !== '') {
				$patternIterator->setPattern($this->getQueryPattern($match['delimiter']));
			}
		}
	}


	private function getQueryPattern(string $delimiter): string
	{
		$delimiterFirstBytePattern = preg_quote($delimiter[0], '~');
		$delimiterPattern = preg_quote($delimiter, '~');

		return /** @lang PhpRegExp */ "
		~
			\\s*+
			(?<leadingComments>
				(?:
						\\s
					|   /\\* (*PRUNE) (?: [^*]++ | \\*(?!/) )*+  \\*/
					|   --[^\\n]*+(?:\\n|\\z)
					|   \\#[^\\n]*+(?:\\n|\\z)
				)*+
			)

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
							|   (?!$delimiterPattern) (\\$(?:[a-zA-Z_\\x80-\\xFF][\\w\\x80-\\xFF]*+)?\\$) (*PRUNE) (?: [^$]++   | (?!\\g{-1})\\$ )*+ \\g{-1}
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
