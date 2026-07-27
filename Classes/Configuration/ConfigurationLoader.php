<?php

declare(strict_types=1);

namespace Lochmueller\Seal\Configuration;

use Lochmueller\Seal\Schema\IndexDefinitionInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;

class ConfigurationLoader
{
    /**
     * @var array<string, IndexDefinitionInterface>
     */
    private array $definitions = [];

    /**
     * @param iterable<IndexDefinitionInterface> $definitions
     */
    public function __construct(
        #[AutowireIterator('seal.indexDefinition')]
        iterable $definitions,
    ) {
        foreach ($definitions as $definition) {
            $this->definitions[$definition->getName()] = $definition;
        }
    }

    public function loadBySite(SiteInterface $site): Configuration
    {
        if (!$site instanceof Site) {
            throw new \InvalidArgumentException('Expected instance of Site, got ' . $site::class);
        }
        return Configuration::createByArray((array) $site->getConfiguration());
    }

    public function getDefinition(string $name): ?IndexDefinitionInterface
    {
        return $this->definitions[$name] ?? null;
    }
}
