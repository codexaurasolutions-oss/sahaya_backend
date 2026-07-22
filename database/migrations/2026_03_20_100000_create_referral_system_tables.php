<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('referral_code')->nullable()->unique()->after('upi_id');
            $table->unsignedBigInteger('referred_by')->nullable()->after('referral_code');
            $table->decimal('referral_earnings', 10, 2)->default(0)->after('referred_by');
        });

        Schema::create('referral_rewards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('referrer_id');
            $table->unsignedBigInteger('referred_id');
            $table->decimal('reward_amount', 10, 2)->default(0);
            $table->enum('reward_type', ['signup', 'first_booking', 'subscription'])->default('signup');
            $table->boolean('is_credited')->default(false);
            $table->timestamp('credited_at')->nullable();
            $table->timestamps();

            $table->foreign('referrer_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('referred_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referral_rewards');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['referral_code', 'referred_by', 'referral_earnings']);
        });
    }
};
