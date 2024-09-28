<?php

declare(strict_types=1);

namespace Kaspiman\RectorRules;

use PhpParser\Node;
use PhpParser\Node\Expr\Exit_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\NodeTraverser;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

class ExitAndDieRector extends AbstractRector
{
	public function __construct(private readonly Suppressor $suppressor)
	{
	}

	public function getRuleDefinition(): RuleDefinition
	{
		return new RuleDefinition(
			'Forbid exit and die',
			[
				new ConfiguredCodeSample(
					'exit',
					'forbid',
					[],
				),
			],
		);
	}

	public function getNodeTypes(): array
	{
		return [Expression::class];
	}

	public function refactor(Node $node): ?int
	{
		/**
		 * @var Expression $node
		 */
		if (!$node->expr instanceof Exit_) {
			return null;
		}

		if ($this->suppressor->isSuppressed($node, $this)) {
			return null;
		}

		return NodeTraverser::REMOVE_NODE;
	}
}
