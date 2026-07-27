<?php

declare(strict_types=1);

namespace Lochmueller\Seal\Engine;

use CmsIg\Seal\Adapter\AdapterFactory;
use CmsIg\Seal\Engine;
use CmsIg\Seal\EngineInterface;
use CmsIg\Seal\Schema\Schema;
use InvalidArgumentException;
use Lochmueller\Seal\Dto\DsnDto;
use Lochmueller\Seal\Event\ResolveAdapterEvent;
use Lochmueller\Seal\Exception\AdapterNotFoundException;
use Lochmueller\Seal\Schema\SchemaBuilder;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;

class EngineFactory
{
    public function __construct(
        protected EventDispatcherInterface $eventDispatcher,
        protected SchemaBuilder $schemaBuilder,
        protected AdapterFactory $adapterFactory,
    ) {}

    public function buildEngineBySite(SiteInterface $site, DsnDto $dsn): EngineInterface
    {
        $schema = $this->getSchema($site, $dsn);
        try {
            $adapter = $this->adapterFactory->createAdapter($dsn->dsn);
        } catch (InvalidArgumentException) {
            $adapter = null;
        }
        $resolveAdapterEvent = new ResolveAdapterEvent($dsn, $site, $adapter);
        $this->eventDispatcher->dispatch($resolveAdapterEvent);

        if ($resolveAdapterEvent->adapter === null) {
            throw new AdapterNotFoundException('No valid adapter found for site "' . $site->getIdentifier() . '"', 23482934);
        }

        return new Engine(
            $resolveAdapterEvent->adapter,
            $schema,
        );
    }

    public function getSchema(SiteInterface $site, DsnDto $dsn): Schema
    {
        return $this->schemaBuilder->getSchema($site, $dsn);
    }
}
