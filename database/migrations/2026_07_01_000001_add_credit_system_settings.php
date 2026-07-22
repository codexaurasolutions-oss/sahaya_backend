<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            ['key' => 'credits_per_job_application', 'value' => '5', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'credits_per_staff_referral', 'value' => '10', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'credit_purchase_price', 'value' => '10', 'created_at' => now(), 'updated_at' => now()],
        ];

        foreach ($settings as $setting) {
            DB::table('settings')->updateOrInsert(
                ['key' => $setting['key']],
                $setting
            );
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'credits_per_job_application',
            'credits_per_staff_referral',
            'credit_purchase_price',
        ])->delete();
    }
};
