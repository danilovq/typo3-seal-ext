<?php

declare(strict_types=1);

namespace Lochmueller\Seal\Tests\Unit\Schema;

use CmsIg\Seal\Schema\Field;
use CmsIg\Seal\Schema\Index;
use CmsIg\Seal\Schema\Schema;
use Lochmueller\Seal\Configuration\ConfigurationLoader;
use Lochmueller\Seal\Dto\DsnDto;
use Lochmueller\Seal\Event\BuildSchemaEvent;
use Lochmueller\Seal\Schema\IndexDefinitionInterface;
use Lochmueller\Seal\Schema\SchemaBuilder;
use Lochmueller\Seal\Tests\Unit\AbstractTest;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;

class SchemaBuilderTest extends AbstractTest
{
    private SchemaBuilder $subject;

    private EventDispatcherInterface $eventDispatcherStub;

    private ConfigurationLoader $configurationLoaderStub;

    private SiteInterface $siteStub;

    private DsnDto $dsnStub;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventDispatcherStub = $this->createStub(EventDispatcherInterface::class);
        $this->eventDispatcherStub->method('dispatch')->willReturnArgument(0);

        $definition = $this->createStub(IndexDefinitionInterface::class);
        $definition->method('getName')->willReturn('default');
        $definition->method('isLanguageAware')->willReturn(false);
        $definition->method('getFields')->willReturn([
            'id' => new Field\IdentifierField('id'),
            'title' => new Field\TextField('title'),
        ]);

        $this->configurationLoaderStub = $this->createStub(ConfigurationLoader::class);
        $this->configurationLoaderStub->method('getDefinition')->willReturn($definition);

        $this->siteStub = $this->createStub(SiteInterface::class);
        $this->siteStub->method('getIdentifier')->willReturn('main');

        $this->dsnStub = new DsnDto('typo3://', 'typo3');

        $this->subject = new SchemaBuilder($this->eventDispatcherStub, $this->configurationLoaderStub);
    }

    public function testDefaultIndexConstant(): void
    {
        self::assertSame('default', SchemaBuilder::DEFAULT_INDEX);
    }

    public function testGetSchemaReturnsSchemaWithDefaultIndex(): void
    {
        $schema = $this->subject->getSchema($this->siteStub, $this->dsnStub);

        self::assertInstanceOf(Schema::class, $schema);
        self::assertArrayHasKey(SchemaBuilder::DEFAULT_INDEX, $schema->indexes);
    }

    public function testGetSchemaDispatchesBuildSchemaEvent(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(BuildSchemaEvent::class))
            ->willReturnArgument(0);

        $subject = new SchemaBuilder($eventDispatcher, $this->configurationLoaderStub);
        $subject->getSchema($this->siteStub, $this->dsnStub);
    }

    public function testGetSchemaReturnsEventModifiedSchema(): void
    {
        $modifiedSchema = new Schema([
            'custom' => new Index('custom', [
                'id' => new Field\IdentifierField('id'),
            ]),
        ]);

        $eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')
            ->willReturnCallback(function (BuildSchemaEvent $event) use ($modifiedSchema): BuildSchemaEvent {
                $event->schema = $modifiedSchema;
                return $event;
            });

        $subject = new SchemaBuilder($eventDispatcher, $this->configurationLoaderStub);
        $schema = $subject->getSchema($this->siteStub, $this->dsnStub);

        self::assertSame($modifiedSchema, $schema);
        self::assertArrayHasKey('custom', $schema->indexes);
        self::assertArrayNotHasKey(SchemaBuilder::DEFAULT_INDEX, $schema->indexes);
    }

}
