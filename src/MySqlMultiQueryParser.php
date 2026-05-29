<?php declare(strict_types = 1);

namespace Nextras\MultiQueryParser;

use Iterator;
use function preg_quote;


class MySqlMultiQueryParser extends BaseMultiQueryParser
{
	/**
	 * @param bool $preserveLeadingComments When true, comments (`--`, `#`, `/* *​/`) that precede a query
	 *                                       are kept as a prefix of the yielded query string instead of
	 *                                       being stripped. Only pure leading whitespace is stripped.
	 */
	public function __construct(
		private bool $preserveLeadingComments = false,
	) {
	}


	public function parseStringStream(Iterator $stream): Iterator
	{
		$patternIterator = new PatternIterator($stream, $this->getQueryPattern(';'));

		foreach ($patternIterator as $match) {
			if (isset($match['delimiter']) && $match['delimiter'] !== '') {
				$patternIterator->setPattern($this->getQueryPattern($match['delimiter']));

			} elseif (isset($match['query']) && $match['query'] !== '') {
				$leadingComments = $this->preserveLeadingComments ? (string) $match['leadingComments'] : '';
				yield $leadingComments . $match['query'];
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
