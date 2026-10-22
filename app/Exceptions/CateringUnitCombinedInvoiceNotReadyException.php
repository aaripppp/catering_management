<?php

namespace App\Exceptions;

use RuntimeException;

class CateringUnitCombinedInvoiceNotReadyException extends RuntimeException
{
    public const MESSAGE = 'Sinkronkan seluruh Tagihan Unit terlebih dahulu sebelum membuat invoice semua unit.';

    public function __construct()
    {
        parent::__construct(self::MESSAGE);
    }
}
