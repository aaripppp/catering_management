<?php

namespace App\Models;

use Database\Factories\CateringMemberPeriodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'catering_member_id',
    'start_date',
    'end_date',
])]
class CateringMemberPeriod extends Model
{
    /** @use HasFactory<CateringMemberPeriodFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<CateringMember, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(CateringMember::class, 'catering_member_id');
    }
}
