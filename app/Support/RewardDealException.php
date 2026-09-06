<?php

namespace App\Support;

use RuntimeException;

class RewardDealException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message = '',
        int $httpStatus = 422
    ) {
        parent::__construct($message !== '' ? $message : $errorCode);
        $this->code = $httpStatus;
    }
}
