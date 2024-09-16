<?php

declare(strict_types=1);

namespace Kaspiman\RectorRules;

use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class GlobalVarsForbidRector extends AbstractRector implements ConfigurableRectorInterface
{
	private array $vars = [];

	private string $forbiddenReplacement;

	public function __construct(private readonly Suppressor $suppressor)
	{
	}

	public function getRuleDefinition(): RuleDefinition
	{
		return new RuleDefinition(
			'Forbid $_SESSION, $_POST and others',
			[
				new ConfiguredCodeSample(
					'$_SESSION',
					'nope',
					[],
				),
			],
		);
	}

	public function getNodeTypes(): array
	{
		return [Variable::class];
	}

	/**
	 * @psalm-suppress MoreSpecificImplementedParamType
	 * @param Variable $node
	 */
	public function refactor(Node $node): ?Node
	{
		if ($this->suppressor->isSuppressed($node, $this)) {
			return null;
		}

		foreach ($this->vars as $var) {
			if (!$this->isName($node, $var)) {
				continue;
			}
			$node->name = $this->forbiddenReplacement;
			return $node;
		}

		return null;
	}

	public function configure(array $configuration): void
	{
		$this->vars = $configuration['vars'];
		$this->forbiddenReplacement = $configuration['forbiddenReplacement'] ?? 'forbidden';
	}
}
