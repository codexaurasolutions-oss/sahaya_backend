<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add job apply limit columns to users table
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'job_apply_count')) {
                $table->unsignedInteger('job_apply_count')->default(0)->after('status');
            }
            if (!Schema::hasColumn('users', 'job_apply_extra_limit')) {
                $table->unsignedInteger('job_apply_extra_limit')->default(0)->after('job_apply_count');
            }
        });

        // Create job_apply_limit_purchases table to track purchases
        if (!Schema::hasTable('job_apply_limit_purchases')) {
            Schema::create('job_apply_limit_purchases', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('razorpay_order_id')->nullable();
                $table->string('razorpay_payment_id')->nullable();
                $table->string('razorpay_signature')->nullable();
                $table->decimal('amount', 10, 2)->default(0);
                $table->unsignedInteger('extra_limit_granted')->default(1);
                $table->string('status')->default('pending'); // pending, success, failed
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            });
        }

        // Insert default settings for job apply limit
        $defaults = [
            [
                'key'         => 'job_apply_free_limit',
                'value'       => '3',
                'title'       => 'Free Job Application Limit',
                'description' => 'Number of job applications a staff can submit for free',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'key'         => 'job_apply_limit_price',
                'value'       => '49',
                'title'       => 'Job Apply Extra Limit Price (INR)',
                'description' => 'Price in INR to purchase 1 extra job application slot',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
        ];

        foreach ($defaults as $setting) {
            $exists = DB::table('settings')->where('key', $setting['key'])->exists();
            if (!$exists) {
                DB::table('settings')->insert($setting);
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'job_apply_count')) {
                $table->dropColumn('job_apply_count');
            }
            if (Schema::hasColumn('users', 'job_apply_extra_limit')) {
                $table->dropColumn('job_apply_extra_limit');
            }
        });
        Schema::dropIfExists('job_apply_limit_purchases');
        DB::table('settings')->whereIn('key', ['job_apply_free_limit', 'job_apply_limit_price'])->delete();
    }
};
