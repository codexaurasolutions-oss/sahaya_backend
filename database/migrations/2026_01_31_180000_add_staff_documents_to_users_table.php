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
            // Document paths
            if (!Schema::hasColumn('users', 'verification_certificate')) {
                $table->string('verification_certificate')->nullable()->after('image');
            }
            if (!Schema::hasColumn('users', 'aadhar_front')) {
                $table->string('aadhar_front')->nullable()->after('verification_certificate');
            }
            if (!Schema::hasColumn('users', 'aadhar_back')) {
                $table->string('aadhar_back')->nullable()->after('aadhar_front');
            }
            
            // Relation field (used for family members or emergency contact contexts)
            if (!Schema::hasColumn('users', 'relation')) {
                $table->string('relation')->nullable()->after('aadhar_back');
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
                'verification_certificate',
                'aadhar_front',
                'aadhar_back',
                'relation'
            ]);
        });
    }
};
