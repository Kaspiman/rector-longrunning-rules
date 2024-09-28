<?php

declare(strict_types=1);

namespace Kaspiman\RectorRules;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PHPStan\Reflection\MethodReflection;
use Rector\PhpParser\Node\BetterNodeFinder;
use Rector\Rector\AbstractRector;
use Rector\Reflection\ReflectionResolver;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class StaticArrowFunctionCheckerRector extends AbstractRector
{
	public function __construct(
		private readonly ReflectionResolver $reflectionResolver,
		private readonly BetterNodeFinder $betterNodeFinder,
	)
	{
	}

	public function getRuleDefinition(): RuleDefinition
	{
		return new RuleDefinition('Отслеживает использование $this в статической стрелочной функции', [new CodeSample(
			<<<'CODE_SAMPLE'
static fn () => $this->method()
CODE_SAMPLE
			,
			<<<'CODE_SAMPLE'
fn (): => $this->method()
CODE_SAMPLE,
		)]);
	}

	public function getNodeTypes(): array
	{
		return [ArrowFunction::class];
	}

	/**
	 * @param ArrowFunction $node
	 */
	public function refactor(Node $node): ?ArrowFunction
	{
		if (!$node->static) {
			return null;
		}

		$nodes = $node instanceof Closure ? $node->stmts : [$node->expr];

		$hasThis = $this->betterNodeFinder->findFirst($nodes, function (Node $subNode): bool {
			if (!$subNode instanceof StaticCall) {
				return $subNode instanceof Variable && $subNode->name === 'this';
			}
			$methodReflection = $this->reflectionResolver->resolveMethodReflectionFromStaticCall($subNode);

			if (!$methodReflection instanceof MethodReflection) {
				return false;
			}

			return !$methodReflection->isStatic();
		});

		if ($hasThis) {
			$node->static = false;

			return $node;
		}

		return null;
	}
}
