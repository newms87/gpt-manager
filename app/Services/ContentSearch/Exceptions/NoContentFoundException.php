<?php

namespace App\Services\ContentSearch\Exceptions;

class NoContentFoundException extends ContentSearchException
{
    public function __construct(string $searchType, string $searchCriteria = '')
    {
        $message = "No content found for {$searchType} search";
        if ($searchCriteria) {
            $message .= ": {$searchCriteria}";
        }

        parent::__construct($message);
    }
}
