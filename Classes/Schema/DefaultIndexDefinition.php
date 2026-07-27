<?php

declare(strict_types=1);

namespace Lochmueller\Seal\Schema;

use CmsIg\Seal\Schema\Field;
use DateTimeImmutable;
use Lochmueller\Index\Event\IndexFileEvent;
use Lochmueller\Index\Event\IndexPageEvent;
use Lochmueller\Index\Traversing\RecordSelection;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Service\ImageService;

class DefaultIndexDefinition implements IndexDefinitionInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly ResourceFactory $resourceFactory,
        private readonly RecordSelection $recordSelection,
    ) {}

    public function getName(): string
    {
        return SchemaBuilder::DEFAULT_SCHEMA;
    }

    public function isLanguageAware(): bool
    {
        return false;
    }

    public function getFields(): array
    {
        return [
            'id' => new Field\IdentifierField('id'),
            'site' => new Field\TextField('site', searchable: false, filterable: true),
            'language' => new Field\TextField('language', searchable: false, filterable: true),
            'uri' => new Field\TextField('uri', searchable: false),
            'location' => new Field\GeoPointField('location', filterable: true),
            'indexdate' => new Field\DateTimeField('indexdate'),
            'title' => new Field\TextField('title', sortable: true),
            'content' => new Field\TextField('content'),
            'tags' => new Field\TextField('tags', multiple: true, filterable: true, facet: true),
            'size' => new Field\IntegerField('size', searchable: false),
            'extension' => new Field\TextField('extension'),
            'preview' => new Field\TextField('preview', searchable: false),
        ];
    }

    public function buildDocument(IndexPageEvent|IndexFileEvent $event): array
    {
        $preview = '';
        $size = 0;
        $extension = '';
        $uri = $event->uri;

        if ($event instanceof IndexFileEvent && isset($event->fileIdentifier)) {
            try {
                $file = $this->resourceFactory->getFileObjectFromCombinedIdentifier($event->fileIdentifier);
                if ($file !== null) {
                    $size = $file->getSize();
                    $extension = $file->getExtension();
                    $uri = $event->site->getBase() . $file->getPublicUrl();
                    $shouldRenderPreview = GeneralUtility::inList($GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext'], strtolower($file->getExtension()));

                    if ($shouldRenderPreview) {
                        $imageService = GeneralUtility::makeInstance(ImageService::class);
                        $image = $imageService->getImage('', $file, false);
                        $processedImage = $imageService->applyProcessingInstructions($image, [
                            'maxWidth' => 200,
                            'maxHeight' => 200,
                        ]);

                        $preview = $event->site->getBase() . $processedImage->getPublicUrl();
                    }
                }
            } catch (\Exception $exception) {
                $this->logger?->error($exception->getMessage(), ['exception' => $exception]);
            }
        } elseif ($event instanceof IndexPageEvent && $uri === '' && $event->site instanceof Site) {
            $arguments = [];
            try {
                $language = $event->site->getLanguageById($event->language);
                $arguments['_language'] = $language;
            } catch (\InvalidArgumentException) {
            }
            $uri = (string) $event->site->getRouter()->generateUri($event->pageUid, $arguments);
        }

        return [
            'id' => $event instanceof IndexPageEvent ? 'p-' . md5($uri) : 'd-' . md5($uri),
            'site' => $event->site->getIdentifier(),
            'language' => isset($event->language) ? (string) $event->language : '0',
            'uri' => $uri,
            'indexdate' => (new DateTimeImmutable())->format(DateTimeImmutable::ATOM),
            'title' => $event->title,
            'content' => (string) preg_replace('/\\s+/', ' ', strip_tags($event->content)),
            'tags' => $this->getTags($event),
            'size' => $size,
            'extension' => $extension,
            'preview' => $preview,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function getTags(IndexFileEvent|IndexPageEvent $event): array
    {
        $tags = [];
        $tags[] = $event instanceof IndexPageEvent ? 'Page' : 'File';
        if ($event instanceof IndexPageEvent && $event->pageUid) {
            $row = $this->recordSelection->findRenderablePage($event->pageUid, $event->language);
            if (isset($row['keywords'])) {
                $tags = array_merge($tags, GeneralUtility::trimExplode(',', $row['keywords'], true));
            }
        }
        return $tags;
    }
}
