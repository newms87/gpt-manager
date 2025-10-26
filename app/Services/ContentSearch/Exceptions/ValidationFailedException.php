<?php

namespace App\Services\ContentSearch\Exceptions;

class ValidationFailedException extends ContentSearchException
{
    public function __construct(string $fieldName, string $value, string $validationReason = '')
    {
        $message = "Validation failed for field '{$fieldName}' with value '{$value}'";
        if ($validationReason) {
            $message .= ": {$validationReason}";
        }

        parent::__construct($message);
    }
}
