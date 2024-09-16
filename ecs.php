<?php

use Symplify\EasyCodingStandard\Config\ECSConfig;
use Symplify\EasyCodingStandard\ValueObject\Set\SetList;

return ECSConfig::configure()
				->withPaths([__DIR__ . '/src', __DIR__ . '/tests'])
				->withSets([
					SetList::COMMON,
					SetList::SYMPLIFY,
					SetList::STRICT,
				])
				->withPhpCsFixerSets(perCS20: true);
