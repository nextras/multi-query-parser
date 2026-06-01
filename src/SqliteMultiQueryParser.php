<?php declare(strict_types = 1);

namespace Nextras\MultiQueryParser;

use Iterator;


class SqliteMultiQueryParser extends BaseMultiQueryParser
{
	protected function parseStringStreamToFragments(Iterator $stream): Iterator
	{
		$patternIterator = new PatternIterator($stream, $this->getQueryPattern());

		foreach ($patternIterator as $match) {
			yield from $this->buildFragments($match['leadingComments'] ?? null, $match['query'] ?? null);
		}
	}


	private function getQueryPattern(): string
	{
		// (*PRUNE) must appear inline (not inside DEFINE subroutines) because PCRE confines
		// backtracking verbs to the subroutine scope. The inner bodies are defined once in
		// DEFINE and referenced after the inline (*PRUNE) to avoid pattern duplication.
		return /** @lang PhpRegExp */ '~
			(?(DEFINE)
				(?<sqI>  (?: \'\' | [^\'] )*+ \' )
				(?<dqI>  (?: "" | [^"] )*+ " )
				(?<btI>  (?: `` | [^`] )*+ ` )
				(?<bkI>  [^\]]*+ (?: \]\] [^\]]*+ )* \] )
				(?<bcI>  (?: [^*]++ | \*(?!/) )*+ \*/ )
				(?<lc>   -- [^\n]*+ )
				(?<skip>
					(?:
							\s
						|   /\* (*PRUNE) (?&bcI)
						|   (?&lc)
					)*+
				)
				(?<stmt>
					(?&skip)
					(?:
							[^;\'"`[/-]++
						|   \' (*PRUNE) (?&sqI)
						|   " (*PRUNE) (?&dqI)
						|   ` (*PRUNE) (?&btI)
						|   \[ (*PRUNE) (?&bkI)
						|   /\* (*PRUNE) (?&bcI)
						|   (?&lc)
						|   (?!;) .
					)++
					;
				)
			)

			\s*+
			(?<leadingComments> (?&skip) )

			(?:
				(?:
					(?<query>
						(?:
							 	[^bB;\'"`[/-]++
							|   \' (*PRUNE) (?&sqI)
							|   " (*PRUNE) (?&dqI)
							|   ` (*PRUNE) (?&btI)
							|   \[ (*PRUNE) (?&bkI)
							|   /\* (*PRUNE) (?&bcI)
							|   (?i:BEGIN) (?!\s*(?:(?i:TRANSACTION|DEFERRED|IMMEDIATE|EXCLUSIVE)\b|;|\z)) (*PRUNE) (?: (?i:\s*END)\s* | (?&stmt) )*
							|   (?&lc)
							|   (?!;) .
						)*+
					)
					(?: ; | \z )
				)
				|
				(?:
					\z
				)
			)
		~xsAS';
	}
}
