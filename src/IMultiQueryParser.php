<?php declare(strict_types = 1);

namespace Nextras\MultiQueryParser;

use Iterator;


interface IMultiQueryParser
{
	public const DEFAULT_CHUNK_SIZE = 64 * 1024;


	/**
	 * @param  positive-int $chunkSize
	 * @return Iterator<string>
	 */
	public function parseFile(string $path, int $chunkSize = self::DEFAULT_CHUNK_SIZE): Iterator;


	/**
	 * @param  resource     $fileStream
	 * @param  positive-int $chunkSize
	 * @return Iterator<string>
	 */
	public function parseFileStream($fileStream, int $chunkSize = self::DEFAULT_CHUNK_SIZE): Iterator;


	/**
	 * @return Iterator<string>
	 */
	public function parseString(string $s): Iterator;


	/**
	 * @param  Iterator<string> $stream
	 * @return Iterator<string>
	 */
	public function parseStringStream(Iterator $stream): Iterator;
}
