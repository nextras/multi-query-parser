<?php declare(strict_types = 1);

namespace Nextras\MultiQueryParser;

use Iterator;
use Nextras\MultiQueryParser\Fragment\Fragment;


/**
 * Decides what happens to the comments found in the parsed SQL stream.
 *
 * The parsers themselves only tokenize the input into a neutral {@see Fragment} stream of queries
 * and comments; the strategy collapses that stream into the final stream of query strings.
 */
interface CommentStrategy
{
	/**
	 * @param  Iterator<Fragment> $fragments
	 * @return Iterator<string>
	 */
	public function apply(Iterator $fragments): Iterator;
}
