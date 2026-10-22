<?php

namespace App\Exceptions;

use RuntimeException;

class CateringUnitInvoiceNotReadyException extends RuntimeException
{
    public const MESSAGE = 'Tagihan unit perlu disinkronkan terlebih dahulu sebelum invoice dibuat.';

    public function __construct()
    {
        parent::__construct(self::MESSAGE);
    }
}
