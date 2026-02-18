<?php declare(strict_types = 1);

namespace Nextras\MultiQueryParser;

use Iterator;


class SqlServerMultiQueryParser implements IMultiQueryParser
{
	use BufferedFileParseTrait;


	public function parseFile(string $path): Iterator
	{
		return $this->parseFileBuffered(
			$path,
			$this->getQueryPattern(),
			static function (array $match): array {
				$query = (isset($match['query']) && $match['query'] !== '') ? $match['query'] : null;
				return [$query, null];
			}
		);
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
