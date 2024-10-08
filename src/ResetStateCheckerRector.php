<?php

declare(strict_types=1);

namespace Kaspiman\RectorRules;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\PropertyProperty;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\FamilyTree\Reflection\FamilyRelationsAnalyzer;
use Rector\NodeAnalyzer\PropertyFetchAnalyzer;
use Rector\NodeManipulator\ClassInsertManipulator;
use Rector\NodeTypeResolver\TypeAnalyzer\ArrayTypeAnalyzer;
use Rector\Php80\NodeAnalyzer\PhpAttributeAnalyzer;
use Rector\PhpParser\Node\BetterNodeFinder;
use Rector\Rector\AbstractRector;
use Rector\ValueObject\MethodName;
use Symfony\Contracts\Service\ResetInterface;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * @psalm-type Properties = array<string, Property>
 * @psalm-type PropertiesProperties = array<string, PropertyProperty>
 * @rector-suppress ResettableStateCheckerRector
 * Идея простая, хотя кода много:
 * ищем классы, исключая DTO, консольные команды, старый код и прочее,
 * в классах ищем поля типа скаляр или массив,
 * ищем изменения этих полей,
 * проверяем, что они уже сбрасываются и имеется интерфейс ResetInterface,
 * добавляем интерфейс и сбрасываем поле в изначальное состояние в методе reset.
 * Конец.
 */
final class ResetStateCheckerRector extends AbstractRector implements ConfigurableRectorInterface
{
    private array $ignoreClassPrefixes = [];

    private array $ignoreClassNames = [];

    private array $ignoreAttributes = [];

    private ?string $className = null;

    private ?string $methodName = null;

    public function __construct(
        private readonly BetterNodeFinder $betterNodeFinder,
        private readonly PropertyFetchAnalyzer $propertyFetchAnalyzer,
        private readonly FamilyRelationsAnalyzer $familyRelationsAnalyzer,
        private readonly ArrayTypeAnalyzer $arrayTypeAnalyzer,
        private readonly ClassInsertManipulator $classInsertManipulator,
        private readonly PhpAttributeAnalyzer $attributeAnalyzer,
        private readonly Suppressor $suppressor,
    ) {}

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Looking for classes with writable properties without Resettable Interface implementing.',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
final class SomeClass
{
    private array $map = [];

    public function preload()
    {
        $this->map = $this->someService->getSomeData();
    }

    public function getMap()
    {
        return $this->map;
    }
}
CODE_SAMPLE
                    ,
                    <<<'CODE_SAMPLE'
final class SomeClass implements ResettableInterface
{
    private array $map = [];

    public function preload()
    {
        $this->map = $this->someService->getSomeData();
    }

    public function getMap()
    {
        return $this->map;
    }

    public function reset()
    {
        $this->map = [];
    }
}
CODE_SAMPLE
                    ,
                ),
            ],
        );
    }

    public function configure(array $configuration): void
    {
        $this->ignoreClassNames = $configuration['ignoreClassNames'] ?? [];
        $this->ignoreClassPrefixes = $configuration['ignoreClassPrefixes'] ?? [];
        $this->ignoreAttributes = $configuration['ignoreAttributes'] ?? [];
        $this->className = $configuration['className'];
        $this->methodName = $configuration['methodName'];
    }

    public function getNodeTypes(): array
    {
        return [Class_::class];
    }

    /**
     * @param Class_ $node
     */
    public function refactor(Node $node): Node|Class_|null
    {
        $classLikeNames = $this->familyRelationsAnalyzer->getClassLikeAncestorNames($node) + [$node->name->name];

        if ($this->isIgnored($node, $classLikeNames)) {
            return null;
        }

        $props = $this->findProperties($node);

        if ($props === []) {
            return null;
        }

        $changedProps = $this->filterChanged($node, $props);

        if ($changedProps === []) {
            return null;
        }

        if (in_array($this->className, $classLikeNames, true)) {
            $resetMethod = $node->getMethod($this->methodName);

            if (! $resetMethod instanceof ClassMethod) {
                $resetMethod = $this->createResetMethod($node);
            }

            $changedProps = $this->filterAlreadyReset($resetMethod, $changedProps);
        } else {
            $resetMethod = $this->createResetMethod($node);
            $node->implements[] = new FullyQualified($this->className);
        }

        if ($changedProps !== []) {
            $this->markAsBad($resetMethod, $changedProps);
        }

        return $node;
    }

    private function isIgnored(Class_ $node, array $classLikeNames): bool
    {
        if ($node->isAnonymous()) {
            return true;
        }

        $func = function ($string, array $check): bool {
            foreach ($check as $s) {
                $pos = str_ends_with($string, $s);

                if ($pos) {
                    return true;
                }
            }

            return false;
        };

        if ($func($node->name->name, $this->ignoreClassPrefixes)) {
            return true;
        }

        if (array_intersect($this->ignoreClassNames, $classLikeNames) !== []) {
            return true;
        }

        if ($this->attributeAnalyzer->hasPhpAttributes($node, $this->ignoreAttributes)) {
            return true;
        }

        return $this->suppressor->isSuppressed($node, $this);
    }

    /**
     * @return Property[]
     */
    private function findProperties(Class_ $node): array
    {
        /** @var Property[] $properties */
        $properties = [];

        foreach ($node->getProperties() as $property) {
            if ($property->isReadonly()) {
                continue;
            }

            if ($property->isPublic()) {
                continue;
            }

            $expr = $property->props[0]->default;

            if (! $expr) {
                continue;
            }

            $type = $this->getType($expr);

            switch (true) {
                case $this->arrayTypeAnalyzer->isArrayType($expr):
                    if ($expr->items !== []) {
                        continue 2;
                    }

                    break;
                case $type->isNull()->yes():
                case $type->isNull()->maybe():
                case $type->isScalar()->maybe():
                case $type->isScalar()->yes():
                    break;
                default:
                    continue 2;
            }

            if ($this->suppressor->isSuppressed($node, $this)) {
                continue;
            }

            $properties[$this->nodeNameResolver->getName($property)] = $property;
        }

        return $properties;
    }

    /**
     * @param Property[] $properties
     * @return PropertyProperty[]
     */
    private function filterChanged(Class_ $node, array $properties): array
    {
        $changed = [];

        foreach ($node->getMethods() as $method) {
            if ($method->name->toLowerString() === MethodName::CONSTRUCT) {
                continue;
            }

            $assigns = $this->betterNodeFinder->findInstanceOf((array) $method->getStmts(), Assign::class);

            foreach ($assigns as $assign) {
                $name = $assign->var instanceof ArrayDimFetch ? $this->getName($assign->var->var) : $this->getName(
                    $assign->var,
                );
                if ($name === null) {
                    continue;
                }

                if ($name === '') {
                    continue;
                }

                if ($name === '0') {
                    continue;
                }

                if (! isset($properties[$name])) {
                    continue;
                }

                $changed[$name] = $properties[$name]->props[0];
            }
        }

        return $changed;
    }

    private function createResetMethod(Class_ $node): ClassMethod
    {
        $resetMethod = $this->nodeFactory->createPublicMethod($this->methodName);
        $this->classInsertManipulator->addAsFirstMethod($node, $resetMethod);

        return $resetMethod;
    }

    /**
     * @param PropertyProperty[] $properties
     */
    private function filterAlreadyReset(ClassMethod $resetMethod, array $properties): array
    {
        $forReset = [];

        foreach ($properties as $name => $property) {
            $found = $this->betterNodeFinder->findFirst(
                (array) $resetMethod->stmts,
                function (Node $node) use ($name): bool {
                    if (! $node instanceof Assign) {
                        return false;
                    }

                    return $this->propertyFetchAnalyzer->isLocalPropertyFetchName($node->var, $name);
                },
            );

            if ($found === null) {
                $forReset[$name] = $property;
            }
        }

        return $forReset;
    }

    /**
     * @param PropertyProperty[] $properties
     */
    private function markAsBad(ClassMethod $resetMethod, array $properties): void
    {
        foreach ($properties as $name => $prop) {
            $resetMethod->stmts[] = new Expression(
                $this->nodeFactory->createPropertyAssignmentWithExpr($name, $prop->default),
            );
        }
    }
}
