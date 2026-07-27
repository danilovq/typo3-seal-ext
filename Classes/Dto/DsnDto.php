<?php

declare(strict_types=1);

namespace Lochmueller\Seal\Dto;

readonly class DsnDto
{
    /**
     * @param array<string, array<mixed>|string> $query
     */
    public function __construct(
        public string  $dsn,
        public string  $scheme,
        #[\SensitiveParameter]
        public ?string $user = null,
        #[\SensitiveParameter]
        public ?string $pass = null,
        public ?string $host = null,
        public ?int    $port = null,
        public ?string $path = null,
        public array   $query = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'dsn' => $this->dsn,
            'scheme' => $this->scheme,
            'user' => $this->user,
            'pass' => $this->pass,
            'host' => $this->host,
            'port' => $this->port,
            'path' => $this->path,
            'query' => $this->query,
        ];
    }
}
