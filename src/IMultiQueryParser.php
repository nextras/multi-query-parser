<?php declare(strict_types = 1);

namespace Nextras\MultiQueryParser;

use Iterator;


interface IMultiQueryParser
{
	/**
	 * @return string[]|Iterator
	 */
	public function parseFile(string $path): Iterator;
}
