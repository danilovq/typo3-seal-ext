<?php

declare(strict_types=1);

namespace Lochmueller\Seal\Tests\Unit\EventListener;

use CmsIg\Seal\EngineInterface;
use DateTimeImmutable;
use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\Enums\IndexType;
use Lochmueller\Index\Event\IndexFileEvent;
use Lochmueller\Index\Event\IndexPageEvent;
use Lochmueller\Seal\EventListener\IndexEventListener;
use Lochmueller\Seal\Schema\IndexDefinitionInterface;
use Lochmueller\Seal\Schema\SchemaBuilder;
use Lochmueller\Seal\Seal;
use Lochmueller\Seal\Tests\Unit\AbstractTest;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;

class IndexEventListenerTest extends AbstractTest
{
    private Seal $sealStub;

    private IndexEventListener $subject;

    private IndexDefinitionInterface $definitionStub;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sealStub = $this->createStub(Seal::class);
        $this->sealStub->method('getIndexNameBySite')->willReturn(SchemaBuilder::DEFAULT_INDEX);

        $this->definitionStub = $this->createStub(IndexDefinitionInterface::class);

        $this->sealStub->method('getDefinitionForSite')->willReturn($this->definitionStub);

        $eventDispatcherStub = $this->createStub(EventDispatcherInterface::class);
        $eventDispatcherStub->method('dispatch')->willReturnArgument(0);

        $this->subject = new IndexEventListener(
            $this->sealStub,
            $eventDispatcherStub,
        );
    }

    public function testInvokeWithPageEventSavesDocument(): void
    {
        $site = $this->createStub(SiteInterface::class);
        $site->method('getIdentifier')->willReturn('main');

        $event = new IndexPageEvent(
            site: $site,
            technology: IndexTechnology::Frontend,
            type: IndexType::Full,
            indexConfigurationRecordId: 1,
            indexProcessId: 'proc-1',
            language: 0,
            title: 'Test Page',
            content: '<p>Hello World</p>',
            pageUid: 42,
            accessGroups: [],
            uri: 'https://example.com/test',
        );

        $this->definitionStub->method('buildDocument')->willReturn([
            'id' => 'p-' . md5('https://example.com/test'),
            'site' => 'main',
            'language' => '0',
            'uri' => 'https://example.com/test',
            'indexdate' => (new DateTimeImmutable())->format(DateTimeImmutable::ATOM),
            'title' => 'Test Page',
            'content' => 'Hello World',
            'tags' => ['Page'],
            'size' => 0,
            'extension' => '',
            'preview' => '',
        ]);

        $engine = $this->createMock(EngineInterface::class);
        $this->sealStub->method('buildEngineBySite')->willReturn($engine);

        $engine->expects(self::once())
            ->method('saveDocument')
            ->with(
                SchemaBuilder::DEFAULT_INDEX,
                self::callback(fn(array $document): bool => $document['title'] === 'Test Page'
                        && $document['content'] === 'Hello World'
                        && $document['site'] === 'main'
                        && $document['language'] === '0'
                        && $document['uri'] === 'https://example.com/test'
                        && str_starts_with($document['id'], 'p-')
                        && in_array('Page', $document['tags'], true)),
            );

        ($this->subject)($event);
    }

    public function testInvokeWithFileEventSavesDocument(): void
    {
        $site = $this->createStub(SiteInterface::class);
        $site->method('getIdentifier')->willReturn('main');

        $event = new IndexFileEvent(
            site: $site,
            indexConfigurationRecordId: 1,
            indexProcessId: 'proc-1',
            title: 'Test File',
            content: 'File content here',
            fileIdentifier: '1:/documents/test.pdf',
            uri: 'https://example.com/file.pdf',
        );

        $this->definitionStub->method('buildDocument')->willReturn([
            'id' => 'd-' . md5('https://example.com/file.pdf'),
            'site' => 'main',
            'language' => '0',
            'uri' => 'https://example.com/file.pdf',
            'indexdate' => (new DateTimeImmutable())->format(DateTimeImmutable::ATOM),
            'title' => 'Test File',
            'content' => 'File content here',
            'tags' => ['File'],
            'size' => 0,
            'extension' => '',
            'preview' => '',
        ]);

        $engine = $this->createMock(EngineInterface::class);
        $this->sealStub->method('buildEngineBySite')->willReturn($engine);

        $engine->expects(self::once())
            ->method('saveDocument')
            ->with(
                SchemaBuilder::DEFAULT_INDEX,
                self::callback(fn(array $document): bool => $document['title'] === 'Test File'
                        && str_starts_with($document['id'], 'd-')
                        && in_array('File', $document['tags'], true)),
            );

        ($this->subject)($event);
    }

    public function testInvokeLogsExceptionOnEngineFailure(): void
    {
        $site = $this->createStub(SiteInterface::class);

        $event = new IndexPageEvent(
            site: $site,
            technology: IndexTechnology::Frontend,
            type: IndexType::Full,
            indexConfigurationRecordId: 1,
            indexProcessId: 'proc-1',
            language: 0,
            title: 'Fail Page',
            content: 'content',
            pageUid: 1,
            accessGroups: [],
        );

        $this->sealStub->method('buildEngineBySite')
            ->willThrowException(new \Exception('Engine error'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with('Engine error', self::arrayHasKey('exception'));

        $this->subject->setLogger($logger);

        ($this->subject)($event);
    }

    public function testInvokeWithPageEventAndEmptyUriResolvesViaRouter(): void
    {
        $router = $this->createStub(\TYPO3\CMS\Core\Routing\RouterInterface::class);
        $router->method('generateUri')->willReturn(new \TYPO3\CMS\Core\Http\Uri('https://example.com/resolved'));

        $site = $this->createStub(Site::class);
        $site->method('getIdentifier')->willReturn('main');
        $site->method('getRouter')->willReturn($router);

        $event = new IndexPageEvent(
            site: $site,
            technology: IndexTechnology::Frontend,
            type: IndexType::Full,
            indexConfigurationRecordId: 1,
            indexProcessId: 'proc-1',
            language: 0,
            title: 'Resolved Page',
            content: 'content',
            pageUid: 42,
            accessGroups: [],
            uri: '',
        );

        $this->definitionStub->method('buildDocument')->willReturnCallback(function (IndexPageEvent $event) {
            $uri = $event->uri;
            if ($uri === '' && $event->site instanceof Site) {
                $arguments = [];
                try {
                    $language = $event->site->getLanguageById($event->language);
                    $arguments['_language'] = $language;
                } catch (\InvalidArgumentException) {
                }
                $uri = (string) $event->site->getRouter()->generateUri($event->pageUid, $arguments);
            }

            return [
                'id' => 'p-' . md5($uri),
                'site' => $event->site->getIdentifier(),
                'language' => (string) $event->language,
                'uri' => $uri,
                'indexdate' => (new DateTimeImmutable())->format(DateTimeImmutable::ATOM),
                'title' => $event->title,
                'content' => (string) preg_replace('/\\s+/', ' ', strip_tags($event->content)),
                'tags' => ['Page'],
                'size' => 0,
                'extension' => '',
                'preview' => '',
            ];
        });

        $engine = $this->createMock(EngineInterface::class);
        $this->sealStub->method('buildEngineBySite')->willReturn($engine);

        $engine->expects(self::once())
            ->method('saveDocument')
            ->with(
                SchemaBuilder::DEFAULT_INDEX,
                self::callback(fn(array $doc): bool => $doc['uri'] === 'https://example.com/resolved'),
            );

        ($this->subject)($event);
    }

    public function testInvokeWithPageEventIncludesKeywordsAsTags(): void
    {
        $site = $this->createStub(SiteInterface::class);
        $site->method('getIdentifier')->willReturn('main');

        $event = new IndexPageEvent(
            site: $site,
            technology: IndexTechnology::Frontend,
            type: IndexType::Full,
            indexConfigurationRecordId: 1,
            indexProcessId: 'proc-1',
            language: 0,
            title: 'Tagged Page',
            content: 'content',
            pageUid: 10,
            accessGroups: [],
            uri: 'https://example.com/tagged',
        );

        $this->definitionStub->method('buildDocument')->willReturn([
            'id' => 'p-' . md5('https://example.com/tagged'),
            'site' => 'main',
            'language' => '0',
            'uri' => 'https://example.com/tagged',
            'indexdate' => (new DateTimeImmutable())->format(DateTimeImmutable::ATOM),
            'title' => 'Tagged Page',
            'content' => 'content',
            'tags' => ['Page', 'typo3', 'search', 'seal'],
            'size' => 0,
            'extension' => '',
            'preview' => '',
        ]);

        $engine = $this->createMock(EngineInterface::class);
        $this->sealStub->method('buildEngineBySite')->willReturn($engine);

        $engine->expects(self::once())
            ->method('saveDocument')
            ->with(
                SchemaBuilder::DEFAULT_INDEX,
                self::callback(fn(array $doc): bool => in_array('Page', $doc['tags'], true)
                        && in_array('typo3', $doc['tags'], true)
                        && in_array('search', $doc['tags'], true)
                        && in_array('seal', $doc['tags'], true)),
            );

        ($this->subject)($event);
    }

    public function testInvokeStripsHtmlTagsFromContent(): void
    {
        $site = $this->createStub(SiteInterface::class);
        $site->method('getIdentifier')->willReturn('main');

        $event = new IndexPageEvent(
            site: $site,
            technology: IndexTechnology::Frontend,
            type: IndexType::Full,
            indexConfigurationRecordId: 1,
            indexProcessId: 'proc-1',
            language: 0,
            title: 'HTML Page',
            content: '<h1>Title</h1>  <p>Paragraph   text</p>',
            pageUid: 1,
            accessGroups: [],
            uri: 'https://example.com',
        );

        $this->definitionStub->method('buildDocument')->willReturn([
            'id' => 'p-' . md5('https://example.com'),
            'site' => 'main',
            'language' => '0',
            'uri' => 'https://example.com',
            'indexdate' => (new DateTimeImmutable())->format(DateTimeImmutable::ATOM),
            'title' => 'HTML Page',
            'content' => 'Title Paragraph text',
            'tags' => ['Page'],
            'size' => 0,
            'extension' => '',
            'preview' => '',
        ]);

        $engine = $this->createMock(EngineInterface::class);
        $this->sealStub->method('buildEngineBySite')->willReturn($engine);

        $engine->expects(self::once())
            ->method('saveDocument')
            ->with(
                SchemaBuilder::DEFAULT_INDEX,
                self::callback(fn(array $doc): bool => $doc['content'] === 'Title Paragraph text'),
            );

        ($this->subject)($event);
    }
}
