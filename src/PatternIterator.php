<?php declare(strict_types = 1);

namespace Nextras\MultiQueryParser;

use Iterator;
use IteratorAggregate;
use Nextras\MultiQueryParser\Exception\RuntimeException;
use function preg_last_error_msg;
use function preg_match;
use function strlen;
use function substr;


/**
 * Applies a regex pattern to a chunked string stream, yielding matches sequentially.
 *
 * Safety mechanism: when a match consumes all remaining data in the buffer and the stream
 * has more chunks, the match is held back (not yielded) until more data is loaded. This
 * prevents yielding incomplete matches at chunk boundaries.
 *
 * Pattern design constraint: patterns with opening/closing delimiter constructs (such as
 * string literals `'...'`, block comments `/*...* /`, or dollar-quoted strings `$$...$$`)
 * must include `(*PRUNE)` after the opening delimiter, e.g. `' (*PRUNE) [^']* '`.
 * Without this, when a chunk boundary falls inside such a construct, the closing delimiter
 * is absent from the buffer, the construct fails to match, and the regex falls back to a
 * generic single-character alternative (e.g. `(?!;) .`). This exposes characters inside the
 * construct (like semicolons inside a string) as false delimiters, producing an incorrect
 * match that terminates in the middle of the buffer — where the safety mechanism cannot
 * detect the problem. The `(*PRUNE)` verb ensures that once the opening delimiter matches,
 * the regex engine commits to the construct — if the closing delimiter is missing (because
 * it is in a later chunk), the overall match fails, causing the iterator to load more data.
 *
 * @implements IteratorAggregate<int, array<mixed>>
 */
class PatternIterator implements IteratorAggregate
{
	/**
	 * @param  Iterator<string> $stream
	 */
	public function __construct(
		private Iterator $stream,
		private string $pattern,
	) {
	}


	public function getPattern(): string
	{
		return $this->pattern;
	}


	public function setPattern(string $pattern): void
	{
		$this->pattern = $pattern;
	}


	public function getIterator(): Iterator
	{
		$s = '';
		$offset = 0;

		while ($this->stream->valid()) {
			$s = substr($s, $offset) . $this->stream->current();
			$this->stream->next();
			$offset = 0;

			while (true) {
				$result = preg_match($this->pattern, $s, $matches, 0, $offset);

				if ($result === false) {
					throw new RuntimeException(preg_last_error_msg());
				}

				if ($result !== 1) {
					break;
				}

				if (strlen($matches[0]) === 0) {
					break 2;
				}

				if (strlen($matches[0]) + $offset === strlen($s) && $this->stream->valid()) {
					break;
				}

				yield $matches;
				$offset += strlen($matches[0]);
			}
		}

		if ($offset !== strlen($s)) {
			throw new RuntimeException("Failed to parse stream, please report an issue.");
		}
	}
}
