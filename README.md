# Правила Rector для долгоживущих PHP-проектов

Правила для анализатора [Rector](https://github.com/rectorphp/rector) для поиска и устранения проблем в долгоживущих
приложениях.

Внедрите Rector в пайплайн вашего проекта и постоянно контролируйте качество кода.

Используйте всё или то, что нужно.

> Репозиторий подготовлен специально для конференции [Podlodka PHP Crew](https://podlodka.io/phpcrew) (сезон 5) в рамках
> доклада "PHP будет долго жить. Переезжаем на Roadrunner" (будет ссылка на запись), презентация [доступна по ссылке](https://docs.google.com/presentation/d/1Er5fT1bgQ2pHXocVDe_aLxxMGMD5Hs1yk_mvHfG_j6Q/edit?usp=sharing).

## Использование

Установите Rector в проект:

```bash
composer require rector/rector --dev
```

Установите правила из этого репозитория:

```bash
composer require kaspiman/rector-longrunning-rules:^1.0 --dev
```

Добавьте и настройте правила в свой конфиг `rector.php`:

```php
<?php

use Rector\Renaming\Rector\ConstFetch\RenameConstantRector;

return static function (RectorConfig $config): void {
    // общие настройки, к примеру директория с проектом...
    $config->paths([
        'src',
    ]);

    // Встроенное правило в Rector, запрещаем константу PHP_SAPI, меняя на несуществующую
    $config->ruleWithConfiguration(RenameConstantRector::class, [
        'PHP_SAPI' => 'FORBID',
    ]);

    // Правила без конфигурации
    $config->rules([
        IncludeRequireRector::class,
        ExitAndDieRector::class,
        EchoForbidRector::class,
    ]);

    // Правила с возможностью настроить поведение

    // Список глобальных переменных находится в файле, можно указать свой набор
    $config->ruleWithConfiguration(GlobalVarsForbidRector::class, [
        'vars' => include 'src/Rector/Resources/global-vars.php'
    ]);

    // Список запрещённых находится в файле, можно указать свой набор или перезаписать список отдельными функциями
    $config->ruleWithConfiguration(ForbiddenFunctionsRector::class, [
        'functions' => include 'src/Rector/Resources/forbidden-functions.php',
    ]);

    // Поиск скрытых состояний
    $config->ruleWithConfiguration(ResetStateCheckerRector::class, [
        'className' => ResetInterface::class, // Имя интерфейса для сброса, например у Symfony это Symfony\Contracts\Service\ResetInterface (https://github.com/symfony/contracts/blob/main/Service/ResetInterface.php)
        'methodName' => 'reset', // имя метод сброса у этого интерфейса
        'ignoreClassNames' => [], // Игнорирование определённых имён классов
        'ignoreClassPrefixes' => [  // Игнорирование определённых префиксов, например DTO и прочих объектов-переносчиков состояния
            'Dto',
            'Mock',
            'Stub',
            'Item',
            'Builder',
            'Collection',
            'Filter',
            'Criteria',
            'Context',
            'Container',
            'Object',
            'Aggregate',
            'Request',
            'Response',
            'Payload',
            'Event',
            'Exception',
            'Result',
            'Message',
            'Config',
            'Param',
            'Params',
            'Data',
        ],
        'ignoreAttributes' => [ // Игнорирование определённых аттрибутов, например сущностей Doctrine
            'Doctrine\ORM\Mapping\Entity',
        ],
    ]);
}
```

Запустите Rector в пробном режиме:

```bash
vendor/bin/rector --dry-run
```

Проверьте предложенные изменения, по необходимости проигнорируйте некоторые из них с помощью аннотации:

```php

public function work(): {
    /**
     * @rector-suppress ForbiddenFunctionsRector
     */
     ini_set('memory_limit', '42G');
}
```

Возможно игнорировать целый класс или его отдельные поля для правила ResetStateCheckerRector:

```php
/**
* @rector-suppress ResetStateCheckerRector
*/
class SomeBadService {
   /**
    * @rector-suppress ResetStateCheckerRector
   */
   private array $cache = [];
}
```

Запустите Rector в рабочем режиме и примените изменения в коду, проверьте корректность:

```bash
vendor/bin/rector
```

**Готово.**
