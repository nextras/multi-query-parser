<?php declare(strict_types = 1);

namespace Nextras\MultiQueryParser\Fragment;


/**
 * A run of one or more comments (and the whitespace interleaved with them).
 */
final class Comment implements Fragment
{
	public function __construct(
		public string $text,
	) {
	}
}
