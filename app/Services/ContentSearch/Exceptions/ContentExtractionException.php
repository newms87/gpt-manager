<?php

namespace App\Services\ContentSearch\Exceptions;

class ContentExtractionException extends ContentSearchException
{
    public function __construct(string $extractionType, string $reason = '', ?\Throwable $previous = null)
    {
        $message = "Failed to extract content using {$extractionType}";
        if ($reason) {
            $message .= ": {$reason}";
        }

        parent::__construct($message, 0, $previous);
    }
}
