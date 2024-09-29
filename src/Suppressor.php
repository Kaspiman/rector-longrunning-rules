<?php

declare(strict_types=1);

namespace Kaspiman\RectorRules;

use PhpParser\Node;
use PHPStan\PhpDocParser\Ast\PhpDoc\GenericTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfoFactory;
use Rector\Contract\Rector\RectorInterface;
use ReflectionClass;

final class Suppressor
{
    public const DOCBLOCK_NAME = 'rector-suppress';

    public const VALUE_SEPARATOR = ' ';

    public function __construct(
        private PhpDocInfoFactory $phpDocInfoFactory,
    ) {}

    public function isSuppressed(Node $node, RectorInterface $rule): bool
    {
        $docBlock = $this->phpDocInfoFactory->createFromNodeOrEmpty($node);

        /** @var ?PhpDocTagNode $suppressBlock */
        $suppressBlock = $docBlock->getByName(self::DOCBLOCK_NAME);

        if (! $suppressBlock) {
            return false;
        }

        $docBlockValue = $suppressBlock->value;

        if (! $docBlockValue instanceof GenericTagValueNode) {
            return false;
        }

        $ruleNames = $docBlockValue->value;

        if (! $ruleNames) {
            return false;
        }

        $suppressedRectorNames = explode(self::VALUE_SEPARATOR, $ruleNames);

        $reflect = new ReflectionClass($rule);

        return in_array($reflect->getShortName(), $suppressedRectorNames, true);
    }
}
