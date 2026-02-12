<?php

declare(strict_types=1);

namespace Raum51\ContaoZoteroBundle\Service;

use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Dekoriert eine Response und erfasst den Body beim ersten Lesen für ApiLogCollector.
 *
 * Liegt in src/Service/, da es die API-Protokollierung unterstützt.
 */
final class LoggingResponseDecorator implements ResponseInterface
{
    public function __construct(
        private readonly ResponseInterface $inner,
        private readonly ApiLogCollector $collector,
        private readonly int $entryIndex,
    ) {
    }

    public function getStatusCode(): int
    {
        return $this->inner->getStatusCode();
    }

    public function getHeaders(bool $throw = true): array
    {
        return $this->inner->getHeaders($throw);
    }

    public function getContent(bool $throw = true): string
    {
        $content = $this->inner->getContent($throw);
        $this->collector->setResponseBody($this->entryIndex, $content);

        return $content;
    }

    public function toArray(bool $throw = true): array
    {
        $content = $this->getContent($throw);

        return json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
    }

    public function cancel(): void
    {
        $this->inner->cancel();
    }

    public function getInfo(?string $type = null): mixed
    {
        return $this->inner->getInfo($type);
    }
}
