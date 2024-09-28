<?php

declare(strict_types=1);

namespace Kaspiman\RectorRules;

use PhpParser\Node;
use PhpParser\Node\Stmt\Echo_;
use PhpParser\NodeTraverser;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class EchoForbidRector extends AbstractRector
{
    public function __construct(private readonly Suppressor $suppressor)
    {
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Forbid echo',
            [
                new ConfiguredCodeSample(
                    'echo',
                    'forbid',
                    [],
                ),
            ],
        );
    }

    public function getNodeTypes(): array
    {
        return [Echo_::class];
    }

    /**
     * @var Echo_ $node
     */
    public function refactor(Node $node): ?int
    {
        if ($this->suppressor->isSuppressed($node, $this)) {
            return null;
        }

        return NodeTraverser::REMOVE_NODE;
    }
}
