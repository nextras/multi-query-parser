<?php declare(strict_types = 1);

namespace Nextras\MultiQueryParser\Strategy;

use Iterator;
use Nextras\MultiQueryParser\CommentStrategy;
use Nextras\MultiQueryParser\Fragment\Query;


final class DropComments implements CommentStrategy
{
	public function apply(Iterator $fragments): Iterator
	{
		foreach ($fragments as $fragment) {
			if ($fragment instanceof Query) {
				yield $fragment->sql;
			}
		}
	}
}
