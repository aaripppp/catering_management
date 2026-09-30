<?php

namespace App\Models;

use App\Enums\CateringAttendanceStatus;
use Database\Factories\CateringAttendanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['catering_member_id', 'attendance_date', 'status'])]
class CateringAttendance extends Model
{
    /** @use HasFactory<CateringAttendanceFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'status' => CateringAttendanceStatus::class,
        ];
    }

    /**
     * @return BelongsTo<CateringMember, $this>
     */
    public function cateringMember(): BelongsTo
    {
        return $this->belongsTo(CateringMember::class);
    }
}
