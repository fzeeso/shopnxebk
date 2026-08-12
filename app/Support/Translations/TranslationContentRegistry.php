<?php

declare(strict_types=1);

namespace App\Support\Translations;

use App\Support\Translations\Contracts\TranslationContentHandler;
use InvalidArgumentException;

final class TranslationContentRegistry
{
    /** @var array<string, TranslationContentHandler> */
    private array $handlers = [];

    /** @param iterable<TranslationContentHandler> $handlers */
    public function __construct(iterable $handlers)
    {
        foreach ($handlers as $handler) {
            $type = $handler->contentType();
            if (preg_match('/^[a-z][a-z0-9_-]{0,79}$/D', $type) !== 1) {
                throw new InvalidArgumentException("Invalid translation content type [{$type}].");
            }
            if (isset($this->handlers[$type])) {
                throw new InvalidArgumentException("Duplicate translation content handler [{$type}].");
            }
            $this->handlers[$type] = $handler;
        }
    }

    public function for(string $contentType): TranslationContentHandler
    {
        return $this->handlers[$contentType]
            ?? throw new InvalidArgumentException("Unsupported translation content type [{$contentType}].");
    }
}
