<?php

declare(strict_types=1);

namespace Lochmueller\Seal\Dashboard\Provider;

use Lochmueller\Seal\Seal;
use ReflectionProperty;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Dashboard\Widgets\ListDataProviderInterface;

class IndexDocumentCountDataProvider implements ListDataProviderInterface
{
    public function __construct(
        private readonly Seal $seal,
        private readonly SiteFinder $siteFinder,
    ) {}

    /**
     * @return array<int, string>
     */
    public function getItems(): array
    {
        $sites = $this->siteFinder->getAllSites();

        if ($sites === []) {
            return [];
        }

        $items = [];

        foreach ($sites as $site) {
            try {
                $engine = $this->seal->buildEngineBySite($site);
                $schema = $this->seal->getSchemaForSite($site);

                $reflectionProperty = new ReflectionProperty($engine, 'adapter');
                $adapter = $reflectionProperty->getValue($engine);
                $adapterClassName = $adapter::class;

                foreach (array_keys($schema->indexes) as $indexName) {
                    $count = $engine->countDocuments($indexName);
                    $items[] = implode(' / ', [
                        $site->getIdentifier(),
                        $indexName,
                        (string) $count,
                        $adapterClassName,
                    ]);
                }
            } catch (\Exception $exception) {
                $items[] = implode(' / ', [
                    $site->getIdentifier(),
                    'Error',
                    $exception->getMessage(),
                ]);
            }
        }

        return $items;
    }
}
