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
