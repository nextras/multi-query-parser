<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace Nextras\MultiQueryParser;

use ArrayIterator;
use Nextras\MultiQueryParser\Fragment\Comment;
use Nextras\MultiQueryParser\Fragment\Fragment;
use Nextras\MultiQueryParser\Fragment\Query;
use Nextras\MultiQueryParser\Strategy\DropComments;
use Nextras\MultiQueryParser\Strategy\KeepLeadingComments;
use Tester\Assert;
use Tester\TestCase;


require_once __DIR__ . '/../bootstrap.php';


class CommentStrategyTest extends TestCase
{
	public function testDropCommentsDropsComments(): void
	{
		$result = $this->apply(new DropComments(), [
			new Comment('-- a'),
			new Query('SELECT 1'),
			new Comment('-- b'),
			new Query('SELECT 2'),
			new Comment('-- trailing'),
		]);

		Assert::same(['SELECT 1', 'SELECT 2'], $result);
	}


	public function testKeepLeadingCommentsPrependsComments(): void
	{
		$result = $this->apply(new KeepLeadingComments(), [
			new Comment("-- a\n"),
			new Query('SELECT 1'),
			new Comment("-- b\n"),
			new Query('SELECT 2'),
		]);

		Assert::same(["-- a\nSELECT 1", "-- b\nSELECT 2"], $result);
	}


	public function testKeepLeadingCommentsWithoutComments(): void
	{
		$result = $this->apply(new KeepLeadingComments(), [
			new Query('SELECT 1'),
			new Query('SELECT 2'),
		]);

		Assert::same(['SELECT 1', 'SELECT 2'], $result);
	}


	public function testKeepLeadingCommentsDropsTrailingComment(): void
	{
		$result = $this->apply(new KeepLeadingComments(), [
			new Query('SELECT 1'),
			new Comment('-- trailing'),
		]);

		Assert::same(['SELECT 1'], $result);
	}


	/**
	 * @param  list<Fragment> $fragments
	 * @return list<string>
	 */
	private function apply(CommentStrategy $strategy, array $fragments): array
	{
		return iterator_to_array($strategy->apply(new ArrayIterator($fragments)), false);
	}
}


(new CommentStrategyTest())->run();
