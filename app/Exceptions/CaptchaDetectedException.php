<?php

namespace App\Exceptions;

use Exception;

class CaptchaDetectedException extends Exception
{
    public function __construct(string $message = 'Капча или блокировка обнаружена', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
