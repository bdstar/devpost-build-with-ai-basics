<?php

namespace App\Services;

use RuntimeException;

class DatasetException extends RuntimeException
{
    /** @param list<string> $problems */
    public function __construct(string $message, public readonly array $problems = [])
    {
        parent::__construct($message);
    }
}
