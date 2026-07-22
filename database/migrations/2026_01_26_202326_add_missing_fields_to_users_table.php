<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // Aadhaar Verification Fields
            if (!Schema::hasColumn('users', 'aadhar_number')) {
                $table->string('aadhar_number')->nullable()->after('phone_number');
            }
            if (!Schema::hasColumn('users', 'aadhar__verify')) {
                $table->boolean('aadhar__verify')->default(0)->after('aadhar_number');
            }
            if (!Schema::hasColumn('users', 'aadhar__verify_otp')) {
                $table->string('aadhar__verify_otp')->nullable()->after('aadhar__verify');
            }
            if (!Schema::hasColumn('users', 'aadhar_number_otp_expire_at')) {
                $table->timestamp('aadhar_number_otp_expire_at')->nullable()->after('aadhar__verify_otp');
            }
            if (!Schema::hasColumn('users', 'aadhar__verify_at')) {
                $table->timestamp('aadhar__verify_at')->nullable()->after('aadhar_number_otp_expire_at');
            }

            // Profile Progress Fields
            if (!Schema::hasColumn('users', 'step')) {
                $table->integer('step')->default(0)->after('is_verified');
            }
            if (!Schema::hasColumn('users', 'is_staff_added')) {
                $table->boolean('is_staff_added')->default(0)->after('step');
            }
            
            // Other useful fields used in the app
            if (!Schema::hasColumn('users', 'business_name')) {
                $table->string('business_name')->nullable();
            }
            if (!Schema::hasColumn('users', 'added_by')) {
                $table->integer('added_by')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'aadhar_number', 
                'aadhar__verify', 
                'aadhar__verify_otp', 
                'aadhar_number_otp_expire_at', 
                'aadhar__verify_at',
                'step',
                'is_staff_added',
                'business_name',
                'added_by'
            ]);
        });
    }
};
