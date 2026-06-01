<?php declare(strict_types = 1);

namespace Nextras\MultiQueryParser\Fragment;


/**
 * A single SQL query (without its terminating delimiter).
 */
final class Query implements Fragment
{
	public function __construct(
		public string $sql,
	) {
	}
}
