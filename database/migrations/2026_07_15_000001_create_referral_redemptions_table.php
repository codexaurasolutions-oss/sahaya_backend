<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('points_redeemed', 12, 2);
            $table->unsignedInteger('credits_granted');
            $table->decimal('points_per_credit', 12, 2);
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        $legacyPoints = DB::table('settings')
            ->where('key', 'credits_per_staff_referral')
            ->value('value') ?? 10;

        DB::table('settings')->updateOrInsert(
            ['key' => 'points_per_staff_referral'],
            [
                'title' => 'Points Per Staff Referral',
                'description' => 'Points awarded for each successful staff referral',
                'value' => $legacyPoints,
                'input_type' => 'number',
                'editable' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        DB::table('settings')->updateOrInsert(
            ['key' => 'staff_referral_points_per_credit'],
            [
                'title' => 'Staff Referral Points Per Credit',
                'description' => 'Referral points required to redeem one job credit',
                'value' => 10,
                'input_type' => 'number',
                'editable' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_redemptions');

        DB::table('settings')->whereIn('key', [
            'points_per_staff_referral',
            'staff_referral_points_per_credit',
        ])->delete();
    }
};
