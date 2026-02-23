<?php declare(strict_types = 1);

namespace Nextras\MultiQueryParser;

use ArrayIterator;
use Iterator;
use Nextras\MultiQueryParser\Exception\RuntimeException;
use function feof;
use function fopen;
use function fread;


abstract class BaseMultiQueryParser implements IMultiQueryParser
{
	/**
	 * @param  positive-int $chunkSize
	 * @return Iterator<string>
	 */
	public function parseFile(string $path, int $chunkSize = self::DEFAULT_CHUNK_SIZE): Iterator
	{
		$handle = @fopen($path, 'rb');

		if ($handle === false) {
			throw new RuntimeException("Cannot open file '$path'.");
		}

		return $this->parseFileStream($handle, $chunkSize);
	}


	/**
	 * @param  resource     $fileStream
	 * @param  positive-int $chunkSize
	 * @return Iterator<string>
	 */
	public function parseFileStream($fileStream, int $chunkSize = self::DEFAULT_CHUNK_SIZE): Iterator
	{
		return $this->parseStringStream($this->toStringStream($fileStream, $chunkSize));
	}


	/**
	 * @return Iterator<string>
	 */
	public function parseString(string $s): Iterator
	{
		return $this->parseStringStream(new ArrayIterator([$s]));
	}


	/**
	 * @param  Iterator<string> $stream
	 * @return Iterator<string>
	 */
	abstract public function parseStringStream(Iterator $stream): Iterator;


	/**
	 * @param  resource     $fileStream
	 * @param  positive-int $chunkSize
	 * @return Iterator<string>
	 */
	private function toStringStream($fileStream, int $chunkSize): Iterator
	{
		while (!feof($fileStream)) {
			$chunk = fread($fileStream, $chunkSize);

			if ($chunk === false) {
				throw new RuntimeException('Error reading file stream.');
			}

			yield $chunk;
		}
	}
}
