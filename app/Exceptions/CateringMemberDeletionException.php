<?php

namespace App\Exceptions;

use RuntimeException;

class CateringMemberDeletionException extends RuntimeException
{
    public static function inconsistentFinancialOwnership(): self
    {
        return new self(
            'Peserta tidak dapat dihapus karena ditemukan relasi data keuangan yang tidak konsisten. Tidak ada data yang dihapus.'
        );
    }
}
