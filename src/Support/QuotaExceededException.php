<?php

namespace Board\PluginShelf\Support;

/**
 * Raised when storing one more item would tip the board over its quota; the
 * offending item's name feeds the user-facing error.
 */
class QuotaExceededException extends \RuntimeException
{
    public function __construct(public readonly string $itemName)
    {
        parent::__construct("Quota exceeded while storing [{$itemName}].");
    }
}
