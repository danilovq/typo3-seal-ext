<?php

declare(strict_types=1);

namespace Lochmueller\Seal;

use CmsIg\Seal\EngineInterface;
use CmsIg\Seal\Schema\Schema;
use Lochmueller\Seal\Configuration\ConfigurationLoader;
use Lochmueller\Seal\Dto\DsnDto;
use Lochmueller\Seal\Engine\EngineFactory;
use Lochmueller\Seal\Schema\IndexDefinitionInterface;
use Lochmueller\Seal\Schema\SchemaBuilder;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

class Seal
{
    /**
     * @var array<string, EngineInterface>
     */
    private array $requestCache = [];

    /**
     * @var array<string, DsnDto>
     */
    private array $dsnCache = [];

    public function __construct(
        protected EngineFactory $engineFactory,
        protected ConfigurationLoader $configurationLoader,
        protected DsnParser $dsnParser,
    ) {}

    public function buildEngineBySite(SiteInterface $site): EngineInterface
    {
        if (isset($this->requestCache[$site->getIdentifier()])) {
            return $this->requestCache[$site->getIdentifier()];
        }

        $dsn = $this->getParsedDsn($site);
        $engine = $this->engineFactory->buildEngineBySite($site, $dsn);

        $this->requestCache[$site->getIdentifier()] = $engine;
        return $engine;
    }

    public function getIndexNameBySite(SiteInterface $site, ?SiteLanguage $language = null): string
    {
        $dsn = $this->getParsedDsn($site);
        $baseName = (isset($dsn->query['index']) && is_string($dsn->query['index'])) ? $dsn->query['index'] : SchemaBuilder::DEFAULT_INDEX;
        $definition = $this->getDefinitionForSite($site);

        if ($definition !== null && $definition->isLanguageAware() && $language !== null) {
            return $baseName . '_' . $language->getLocale()->getLanguageCode();
        }

        return $baseName;
    }

    public function getDefinitionForSite(SiteInterface $site): ?IndexDefinitionInterface
    {
        $dsn = $this->getParsedDsn($site);
        $definitionName = (isset($dsn->query['definition']) && is_string($dsn->query['definition'])) ? $dsn->query['definition'] : SchemaBuilder::DEFAULT_SCHEMA;

        $definition = $this->configurationLoader->getDefinition($definitionName);
        if ($definition === null && $definitionName !== SchemaBuilder::DEFAULT_SCHEMA) {
            $definition = $this->configurationLoader->getDefinition(SchemaBuilder::DEFAULT_SCHEMA);
        }

        return $definition;
    }

    public function hasLanguageField(SiteInterface $site): bool
    {
        $definition = $this->getDefinitionForSite($site);

        return $definition !== null && isset($definition->getFields()['language']);
    }

    public function getSchemaForSite(SiteInterface $site): Schema
    {
        $dsn = $this->getParsedDsn($site);
        return $this->engineFactory->getSchema($site, $dsn);
    }

    private function getParsedDsn(SiteInterface $site): DsnDto
    {
        if (isset($this->dsnCache[$site->getIdentifier()])) {
            return $this->dsnCache[$site->getIdentifier()];
        }

        $config = $this->configurationLoader->loadBySite($site);
        $dsnDto = $this->dsnParser->parse($config->searchDsn);

        return $this->dsnCache[$site->getIdentifier()] = $dsnDto;
    }
}
