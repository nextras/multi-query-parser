<?php declare(strict_types = 1);

namespace Nextras\MultiQueryParser;

use ArrayIterator;
use Iterator;
use Nextras\MultiQueryParser\Exception\RuntimeException;
use Nextras\MultiQueryParser\Fragment\Comment;
use Nextras\MultiQueryParser\Fragment\Fragment;
use Nextras\MultiQueryParser\Fragment\Query;
use Nextras\MultiQueryParser\Strategy\DropComments;
use function feof;
use function fopen;
use function fread;


abstract class BaseMultiQueryParser implements IMultiQueryParser
{
	private CommentStrategy $commentStrategy;


	public function __construct(?CommentStrategy $commentStrategy = null)
	{
		$this->commentStrategy = $commentStrategy ?? new DropComments();
	}


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
	public function parseStringStream(Iterator $stream): Iterator
	{
		return $this->commentStrategy->apply($this->parseStringStreamToFragments($stream));
	}


	/**
	 * @param  Iterator<string> $stream
	 * @return Iterator<Fragment>
	 */
	abstract protected function parseStringStreamToFragments(Iterator $stream): Iterator;


	/**
	 * @return Iterator<Fragment>
	 */
	protected function buildFragments(?string $leadingComments, ?string $query): Iterator
	{
		if ($leadingComments !== null && $leadingComments !== '') {
			yield new Comment($leadingComments);
		}

		if ($query !== null && $query !== '') {
			yield new Query($query);
		}
	}


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
