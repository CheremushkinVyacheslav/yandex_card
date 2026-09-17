<?php

namespace App\Exceptions;

use Exception;

class SourceChangedException extends Exception
{
    public function __construct(string $message = 'Разметка источника изменилась', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
