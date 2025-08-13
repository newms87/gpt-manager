<?php

namespace App\Services\ContentSearch\Exceptions;

class InvalidSearchParametersException extends ContentSearchException
{
    public function __construct(string $parameter, string $reason = '')
    {
        $message = "Invalid search parameter: {$parameter}";
        if ($reason) {
            $message .= " - {$reason}";
        }
        
        parent::__construct($message);
    }
}