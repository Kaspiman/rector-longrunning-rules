<?php

declare(strict_types=1);

namespace Kaspiman\RectorRules;

use PhpParser\Node;
use PhpParser\Node\Expr\Include_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class IncludeRequireRector extends AbstractRector
{
    public function __construct(
        private readonly Suppressor $suppressor,
    ) {}

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'include_once and require_once without _once',
            [new ConfiguredCodeSample('include_once', 'include', [])],
        );
    }

    public function getNodeTypes(): array
    {
        return [Include_::class];
    }

    public function refactor(Node $node): ?Node
    {
        /**
         * @var Include_ $node
         */
        if ($this->suppressor->isSuppressed($node, $this)) {
            return null;
        }

        if ($node->type === Include_::TYPE_INCLUDE_ONCE) {
            return new Include_($node->expr, Include_::TYPE_INCLUDE);
        }

        if ($node->type === Include_::TYPE_REQUIRE_ONCE) {
            return new Include_($node->expr, Include_::TYPE_REQUIRE);
        }

        return null;
    }
}
