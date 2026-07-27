<?php

declare(strict_types=1);

namespace Lochmueller\Seal\Tests\Unit\Engine;

use CmsIg\Seal\Adapter\AdapterFactory;
use CmsIg\Seal\Adapter\AdapterFactoryInterface;
use CmsIg\Seal\Adapter\AdapterInterface;
use CmsIg\Seal\EngineInterface;
use CmsIg\Seal\Schema\Field;
use CmsIg\Seal\Schema\Index;
use CmsIg\Seal\Schema\Schema;
use Lochmueller\Seal\Dto\DsnDto;
use Lochmueller\Seal\Engine\EngineFactory;
use Lochmueller\Seal\Event\ResolveAdapterEvent;
use Lochmueller\Seal\Exception\AdapterNotFoundException;
use Lochmueller\Seal\Schema\SchemaBuilder;
use Lochmueller\Seal\Tests\Unit\AbstractTest;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;

class EngineFactoryTest extends AbstractTest
{
    private SchemaBuilder $schemaBuilderStub;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaBuilderStub = $this->createStub(SchemaBuilder::class);
        $this->schemaBuilderStub->method('getSchema')->willReturn(new Schema([
            SchemaBuilder::DEFAULT_INDEX => new Index(SchemaBuilder::DEFAULT_INDEX, [
                'id' => new Field\IdentifierField('id'),
            ]),
        ]));
    }

    public function testBuildEngineBySiteReturnsEngineWhenAdapterFactoryMatches(): void
    {
        $dsn = new DsnDto('elasticsearch://localhost:9200', 'elasticsearch', host: 'localhost', port: 9200);

        $adapter = $this->createStub(AdapterInterface::class);
        $adapterFactory = new class ($adapter) implements AdapterFactoryInterface {
            private AdapterInterface $adapter;
            public array $dsn = [];

            public function __construct(AdapterInterface $adapter)
            {
                $this->adapter = $adapter;
            }

            public static function getName(): string
            {
                return 'elasticsearch';
            }

            public function createAdapter(array $dsn): AdapterInterface
            {
                $this->dsn = $dsn;
                return $this->adapter;
            }
        };

        $eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')
            ->willReturnCallback(fn(ResolveAdapterEvent $event): ResolveAdapterEvent => $event);

        $subject = new EngineFactory(
            $eventDispatcher,
            $this->schemaBuilderStub,
            new AdapterFactory([
                'elasticsearch' => $adapterFactory,
            ]),
        );

        $site = $this->createStub(SiteInterface::class);
        $site->method('getIdentifier')->willReturn('main');

        $result = $subject->buildEngineBySite($site, $dsn);

        self::assertInstanceOf(EngineInterface::class, $result);

        // Verify AdapterFactory was called with correct DSN
        self::assertNotEmpty($adapterFactory->dsn);
        self::assertEquals('elasticsearch', $adapterFactory->dsn['scheme']);
        self::assertEquals('localhost', $adapterFactory->dsn['host']);
        self::assertEquals(9200, $adapterFactory->dsn['port']);
    }

    public function testBuildEngineBySiteThrowsExceptionWhenNoAdapterFound(): void
    {
        $dsn = new DsnDto('unknown://', 'unknown');

        $eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')
            ->willReturnCallback(fn(ResolveAdapterEvent $event): ResolveAdapterEvent => $event);

        $subject = new EngineFactory(
            $eventDispatcher,
            $this->schemaBuilderStub,
            new AdapterFactory([]),
        );

        $site = $this->createStub(SiteInterface::class);
        $site->method('getIdentifier')->willReturn('main');

        $this->expectException(AdapterNotFoundException::class);
        $this->expectExceptionCode(23482934);

        $subject->buildEngineBySite($site, $dsn);
    }

    public function testBuildEngineBySiteUsesEventDispatcherToResolveAdapter(): void
    {
        $dsn = new DsnDto('custom://', 'custom');

        $adapter = $this->createStub(AdapterInterface::class);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(ResolveAdapterEvent::class))
            ->willReturnCallback(function (ResolveAdapterEvent $event) use ($adapter): ResolveAdapterEvent {
                $event->adapter = $adapter;
                return $event;
            });

        $subject = new EngineFactory(
            $eventDispatcher,
            $this->schemaBuilderStub,
            new AdapterFactory([]),
        );

        $site = $this->createStub(SiteInterface::class);
        $site->method('getIdentifier')->willReturn('main');

        $result = $subject->buildEngineBySite($site, $dsn);

        self::assertInstanceOf(EngineInterface::class, $result);
    }

    public function testBuildEngineBySiteExceptionContainsSiteIdentifier(): void
    {
        $dsn = new DsnDto('missing://', 'missing');

        $eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnArgument(0);

        $subject = new EngineFactory(
            $eventDispatcher,
            $this->schemaBuilderStub,
            new AdapterFactory([]),
        );

        $site = $this->createStub(SiteInterface::class);
        $site->method('getIdentifier')->willReturn('my-portal');

        try {
            $subject->buildEngineBySite($site, $dsn);
            self::fail('Expected AdapterNotFoundException was not thrown');
        } catch (AdapterNotFoundException $e) {
            self::assertStringContainsString('my-portal', $e->getMessage());
        }
    }
}
