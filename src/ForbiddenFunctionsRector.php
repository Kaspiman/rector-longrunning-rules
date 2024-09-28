<?php

declare(strict_types=1);

namespace Kaspiman\RectorRules;

use PhpParser\Node;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\PhpParser\Node\Value\ValueResolver;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class ForbiddenFunctionsRector extends AbstractRector implements ConfigurableRectorInterface
{
    private array $functions = [];

    public function __construct(
        private readonly Suppressor $suppressor,
        private readonly ValueResolver $valueResolver,
    )
    {
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Forbid danger functions',
            [
                new ConfiguredCodeSample(
                    'setcookie',
                    'remove that function',
                    [],
                ),
            ],
        );
    }

    public function getNodeTypes(): array
    {
        return [FuncCall::class];
    }

    /**
     * @param FuncCall $node
     */
    public function refactor(Node $node): ?Node
    {
        if ($this->suppressor->isSuppressed($node, $this)) {
            return null;
        }

        foreach ($this->functions as $function) {
            if (!$this->isName($node, $function)) {
                continue;
            }

            if ($this->isName($node, 'print_r')) {
                if ($this->analyzePrintr($node)) {
                    return null;
                }
            }

            $node->name = new Name('forbidden_function');

            return $node;
        }

        return null;
    }

    /**
     * @param FuncCall $node
     * @return bool
     */
    private function analyzePrintr(Node $node): bool
    {
        if (count($node->args) < 2) {
            return false;
        }

        $returnArg = $node->args[1];

        if (!$returnArg->value instanceof ConstFetch) {
            return false;
        }

        return $this->valueResolver->isTrue($returnArg->value);
    }

    public function configure(array $configuration): void
    {
        $this->functions = $configuration['functions'];
    }
}
