<?php

namespace App\Exceptions;

use RuntimeException;

class WarehouseException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message, public readonly array $context = [], public readonly int $httpStatus = 409)
    {
        parent::__construct($message);
    }
}
