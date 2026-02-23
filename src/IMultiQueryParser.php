<?php declare(strict_types = 1);

namespace Nextras\MultiQueryParser;

use Iterator;
use Nextras\MultiQueryParser\Exception\RuntimeException;


interface IMultiQueryParser
{
	public const DEFAULT_CHUNK_SIZE = 64 * 1024;


	/**
	 * @param  positive-int $chunkSize
	 * @return Iterator<string>
	 * @throws RuntimeException
	 */
	public function parseFile(string $path, int $chunkSize = self::DEFAULT_CHUNK_SIZE): Iterator;


	/**
	 * @param  resource     $fileStream
	 * @param  positive-int $chunkSize
	 * @return Iterator<string>
	 * @throws RuntimeException
	 */
	public function parseFileStream($fileStream, int $chunkSize = self::DEFAULT_CHUNK_SIZE): Iterator;


	/**
	 * @return Iterator<string>
	 * @throws RuntimeException
	 */
	public function parseString(string $s): Iterator;


	/**
	 * @param  Iterator<string> $stream
	 * @return Iterator<string>
	 * @throws RuntimeException
	 */
	public function parseStringStream(Iterator $stream): Iterator;
}
