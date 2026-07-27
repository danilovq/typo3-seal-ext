<?php

declare(strict_types=1);

namespace Lochmueller\Seal\Tests\Unit;

use CmsIg\Seal\EngineInterface;
use Lochmueller\Seal\Configuration\Configuration;
use Lochmueller\Seal\Configuration\ConfigurationLoader;
use Lochmueller\Seal\DsnParser;
use Lochmueller\Seal\Dto\DsnDto;
use Lochmueller\Seal\Engine\EngineFactory;
use Lochmueller\Seal\Seal;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;

class SealTest extends AbstractTest
{
    private EngineFactory $engineFactoryStub;

    private ConfigurationLoader $configurationLoaderStub;

    private DsnParser $dsnParserStub;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engineFactoryStub = $this->createStub(EngineFactory::class);

        $this->configurationLoaderStub = $this->createStub(ConfigurationLoader::class);
        $this->configurationLoaderStub->method('loadBySite')->willReturn(new Configuration('typo3://', 3, 10));

        $this->dsnParserStub = $this->createStub(DsnParser::class);
        $this->dsnParserStub->method('parse')->willReturn(new DsnDto('typo3://', 'typo3'));
    }

    public function testBuildEngineBySiteReturnsEngineFromFactory(): void
    {
        $engine = $this->createStub(EngineInterface::class);
        $this->engineFactoryStub->method('buildEngineBySite')->willReturn($engine);

        $site = $this->createStub(SiteInterface::class);
        $site->method('getIdentifier')->willReturn('main');

        $subject = new Seal($this->engineFactoryStub, $this->configurationLoaderStub, $this->dsnParserStub);

        $result = $subject->buildEngineBySite($site);

        self::assertSame($engine, $result);
    }

    public function testBuildEngineBySiteCachesResultForSameSite(): void
    {
        $engine = $this->createStub(EngineInterface::class);

        $engineFactory = $this->createMock(EngineFactory::class);
        $engineFactory->expects(self::once())
            ->method('buildEngineBySite')
            ->willReturn($engine);

        $site = $this->createStub(SiteInterface::class);
        $site->method('getIdentifier')->willReturn('main');

        $subject = new Seal($engineFactory, $this->configurationLoaderStub, $this->dsnParserStub);

        $first = $subject->buildEngineBySite($site);
        $second = $subject->buildEngineBySite($site);

        self::assertSame($first, $second);
    }

    public function testBuildEngineBySiteReturnsDifferentEnginesForDifferentSites(): void
    {
        $engineA = $this->createStub(EngineInterface::class);
        $engineB = $this->createStub(EngineInterface::class);

        $siteA = $this->createStub(SiteInterface::class);
        $siteA->method('getIdentifier')->willReturn('site-a');

        $siteB = $this->createStub(SiteInterface::class);
        $siteB->method('getIdentifier')->willReturn('site-b');

        $engineFactory = $this->createStub(EngineFactory::class);
        $engineFactory->method('buildEngineBySite')
            ->willReturnCallback(fn(SiteInterface $site, DsnDto $dsn) => match ($site) {
                $siteA => $engineA,
                $siteB => $engineB,
            });

        $subject = new Seal($engineFactory, $this->configurationLoaderStub, $this->dsnParserStub);

        $resultA = $subject->buildEngineBySite($siteA);
        $resultB = $subject->buildEngineBySite($siteB);

        self::assertSame($engineA, $resultA);
        self::assertSame($engineB, $resultB);
        self::assertNotSame($resultA, $resultB);
    }

    public function testBuildEngineBySiteCachesPerSiteIdentifier(): void
    {
        $engine = $this->createStub(EngineInterface::class);

        $engineFactory = $this->createMock(EngineFactory::class);
        $engineFactory->expects(self::exactly(2))
            ->method('buildEngineBySite')
            ->willReturn($engine);

        $siteA = $this->createStub(SiteInterface::class);
        $siteA->method('getIdentifier')->willReturn('alpha');

        $siteB = $this->createStub(SiteInterface::class);
        $siteB->method('getIdentifier')->willReturn('beta');

        $subject = new Seal($engineFactory, $this->configurationLoaderStub, $this->dsnParserStub);

        // Each site triggers one factory call
        $subject->buildEngineBySite($siteA);
        $subject->buildEngineBySite($siteB);

        // Repeated calls use cache — no additional factory calls
        $subject->buildEngineBySite($siteA);
        $subject->buildEngineBySite($siteB);
    }
}
