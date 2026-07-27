<?php

declare(strict_types=1);

namespace Lochmueller\Seal\Schema;

use CmsIg\Seal\Schema\Field\AbstractField;
use Lochmueller\Index\Event\IndexFileEvent;
use Lochmueller\Index\Event\IndexPageEvent;

interface IndexDefinitionInterface
{
    public function getName(): string;

    public function isLanguageAware(): bool;

    /**
     * @return array<string, AbstractField>
     */
    public function getFields(): array;

    /**
     * @return array<string, mixed>
     */
    public function buildDocument(IndexPageEvent|IndexFileEvent $event): array;
}
