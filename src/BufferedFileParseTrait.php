<?php declare(strict_types = 1);

namespace Nextras\MultiQueryParser;

use Iterator;
use Nextras\MultiQueryParser\Exception\RuntimeException;
use function fclose;
use function feof;
use function fopen;
use function fread;
use function preg_match;
use function strlen;
use function substr;


trait BufferedFileParseTrait
{
	/**
	 * @param callable(array<int|string, string>): array{?string, ?string} $processMatch
	 * @return Iterator<int, string>
	 */
	private function parseFileBuffered(string $path, string $pattern, callable $processMatch): Iterator
	{
		$handle = @fopen($path, 'rb');
		if ($handle === false) {
			throw new RuntimeException("Cannot open file '$path'.");
		}

		try {
			$buffer = '';
			$offset = 0;
			$eof = false;
			$chunkSize = 65536; // 64 KiB

			while (true) {
				// Read more data if buffer is running low and file is not exhausted
				while (!$eof && strlen($buffer) - $offset < $chunkSize) {
					$chunk = fread($handle, $chunkSize);
					if ($chunk === false || $chunk === '') {
						$eof = feof($handle);
						break;
					}
					$buffer .= $chunk;
					$eof = feof($handle);
				}

				if ($offset >= strlen($buffer)) {
					break;
				}

				if (preg_match($pattern, $buffer, $match, 0, $offset) !== 1) {
					break;
				}

				$matchEnd = $offset + strlen($match[0]);

				// Safety check: if the match reaches the end of the buffer and we're not at EOF,
				// read more data and retry — prevents \z from falsely matching at a chunk boundary
				if ($matchEnd >= strlen($buffer) && !$eof) {
					$chunk = fread($handle, $chunkSize);
					if ($chunk !== false && $chunk !== '') {
						$buffer .= $chunk;
						$eof = feof($handle);
						continue; // retry the match with more data
					}
					$eof = true;
				}

				$offset = $matchEnd;

				[$query, $newPattern] = $processMatch($match);

				if ($newPattern !== null) {
					$pattern = $newPattern;
				}

				if ($query !== null) {
					yield $query;
				} elseif ($newPattern === null) {
					// No query and no pattern change means we hit the \z end-of-content branch
					break;
				}

				// Trim consumed content from the buffer to free memory
				if ($offset > $chunkSize) {
					$buffer = substr($buffer, $offset);
					$offset = 0;
				}
			}

			if ($offset !== strlen($buffer)) {
				throw new RuntimeException("Failed to parse file '$path', please report an issue.");
			}
		} finally {
			fclose($handle);
		}
	}
}
