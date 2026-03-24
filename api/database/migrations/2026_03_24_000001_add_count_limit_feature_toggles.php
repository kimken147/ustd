<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('feature_toggles')->insert([
            [
                'id'         => 55,
                'hidden'     => false,
                'enabled'    => false,
                'input'      => json_encode([
                    'type'  => 'boolean',
                    'value' => '',
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id'         => 56,
                'hidden'     => false,
                'enabled'    => false,
                'input'      => json_encode([
                    'type'  => 'boolean',
                    'value' => '',
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('feature_toggles')->whereIn('id', [55, 56])->delete();
    }
};
