<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->applyOrder([
            'Daycare' => 1,
            'TK' => 2,
            'SD' => 3,
            'SMP' => 4,
            'SMA' => 5,
            'TU' => 6,
            'Annur Mart' => 7,
            'Yayasan' => 8,
            'SDM' => 9,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->applyOrder([
            'Daycare' => 1,
            'TU' => 2,
            'TK' => 3,
            'SD' => 4,
            'SMP' => 5,
            'SMA' => 6,
            'Annur Mart' => 7,
            'Yayasan' => 8,
            'SDM' => 9,
        ]);
    }

    /** @param array<string, int> $order */
    private function applyOrder(array $order): void
    {
        foreach ($order as $name => $sortOrder) {
            DB::table('catering_employee_units')
                ->where('name', $name)
                ->update(['sort_order' => $sortOrder]);
        }
    }
};
