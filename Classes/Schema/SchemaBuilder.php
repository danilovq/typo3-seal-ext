<?php

declare(strict_types=1);

namespace Lochmueller\Seal\Schema;

use CmsIg\Seal\Schema\Index;
use CmsIg\Seal\Schema\Schema;
use Lochmueller\Seal\Configuration\ConfigurationLoader;
use Lochmueller\Seal\Dto\DsnDto;
use Lochmueller\Seal\Event\BuildSchemaEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;

class SchemaBuilder
{
    public const DEFAULT_INDEX = 'default';
    public const DEFAULT_SCHEMA = 'default';

    public function __construct(
        protected EventDispatcherInterface $eventDispatcher,
        protected ConfigurationLoader $configurationLoader,
    ) {}

    public function getSchema(SiteInterface $site, DsnDto $dsn): Schema
    {
        $baseName = (isset($dsn->query['index']) && is_string($dsn->query['index'])) ? $dsn->query['index'] : self::DEFAULT_INDEX;
        $definitionName = (isset($dsn->query['definition']) && is_string($dsn->query['definition'])) ? $dsn->query['definition'] : self::DEFAULT_SCHEMA;

        $definition = $this->configurationLoader->getDefinition($definitionName);
        if ($definition === null && $definitionName !== self::DEFAULT_SCHEMA) {
            $definition = $this->configurationLoader->getDefinition(self::DEFAULT_SCHEMA);
        }

        if ($definition === null) {
            return new Schema([]);
        }

        $fields = $definition->getFields();
        $indexes = [];

        if ($definition->isLanguageAware()) {
            $indexSupportsLocale = self::indexSupportsLocale();
            foreach ($site->getLanguages() as $language) {
                $languageCode = $language->getLocale()->getLanguageCode();
                $name = $baseName . '_' . $languageCode;
                $indexes[$name] = $indexSupportsLocale
                    ? new Index($name, $fields, locale: $languageCode) // @phpstan-ignore argument.unknown
                    : new Index($name, $fields, options: ['locale' => $languageCode]);
            }
        } else {
            $indexes[$baseName] = new Index($baseName, $fields);
        }

        $schema = new Schema($indexes);
        $event = new BuildSchemaEvent($schema);
        $this->eventDispatcher->dispatch($event);
        return $event->schema;
    }

    private static function indexSupportsLocale(): bool
    {
        static $supported = null;
        if ($supported !== null) {
            return $supported;
        }
        $constructor = (new \ReflectionClass(Index::class))->getConstructor();
        if ($constructor === null) {
            return $supported = false;
        }
        foreach ($constructor->getParameters() as $param) {
            if ($param->getName() === 'locale') {
                return $supported = true;
            }
        }
        return $supported = false;
    }
}
