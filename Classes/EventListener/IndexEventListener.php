<?php

declare(strict_types=1);

namespace Lochmueller\Seal\EventListener;

use Lochmueller\Index\Event\IndexFileEvent;
use Lochmueller\Index\Event\IndexPageEvent;
use Lochmueller\Seal\Event\BeforeSaveDocumentEvent;
use Lochmueller\Seal\Seal;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Attribute\AsEventListener;

class IndexEventListener implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly Seal $seal,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    #[AsEventListener('seal-index')]
    public function __invoke(IndexFileEvent|IndexPageEvent $event): void
    {
        try {
            $engine = $this->seal->buildEngineBySite($event->site);
            $definition = $this->seal->getDefinitionForSite($event->site);

            if ($definition === null) {
                throw new \RuntimeException('No index definition found for site "' . $event->site->getIdentifier() . '"', 1734567890);
            }

            $document = $definition->buildDocument($event);
            $language = $event instanceof IndexPageEvent
                ? $event->site->getLanguageById($event->language)
                : $event->site->getDefaultLanguage();
            $indexName = $this->seal->getIndexNameBySite($event->site, $language);

            $beforeSaveEvent = new BeforeSaveDocumentEvent($document, $event->site, $indexName);
            $this->eventDispatcher->dispatch($beforeSaveEvent);

            $engine->saveDocument($beforeSaveEvent->indexName, $beforeSaveEvent->document);
        } catch (\Exception $exception) {
            $this->logger?->error($exception->getMessage(), ['exception' => $exception]);
        }
    }
}
