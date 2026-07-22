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
            if (!Schema::hasColumn('users', 'aadhar_reference_id')) {
                $table->string('aadhar_reference_id')->nullable()->after('aadhar__verify_otp');
            }
            if (!Schema::hasColumn('users', 'aadhar_name')) {
                $table->string('aadhar_name')->nullable()->after('aadhar_reference_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['aadhar_reference_id', 'aadhar_name']);
        });
    }
};
