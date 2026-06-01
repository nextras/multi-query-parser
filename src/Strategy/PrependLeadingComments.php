<?php declare(strict_types = 1);

namespace Nextras\MultiQueryParser\Strategy;

use Iterator;
use Nextras\MultiQueryParser\CommentStrategy;
use Nextras\MultiQueryParser\Fragment\Comment;
use Nextras\MultiQueryParser\Fragment\Query;


/**
 * Prepends the comments preceding a query to that query, keeping their original formatting.
 *
 * Comments not followed by any query (e.g. a trailing comment at the end of input) are dropped,
 * since there is no query to attach them to.
 */
final class PrependLeadingComments implements CommentStrategy
{
	public function apply(Iterator $fragments): Iterator
	{
		$leadingComments = '';

		foreach ($fragments as $fragment) {
			if ($fragment instanceof Comment) {
				$leadingComments .= $fragment->text;

			} elseif ($fragment instanceof Query) {
				yield $leadingComments . $fragment->sql;
				$leadingComments = '';
			}
		}
	}
}
