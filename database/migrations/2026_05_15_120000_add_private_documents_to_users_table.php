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
            // Private Document fields
            if (!Schema::hasColumn('users', 'employer_aadhar_front')) {
                $table->string('employer_aadhar_front')->nullable()->after('aadhar_back');
            }
            if (!Schema::hasColumn('users', 'employer_aadhar_back')) {
                $table->string('employer_aadhar_back')->nullable()->after('employer_aadhar_front');
            }
            if (!Schema::hasColumn('users', 'employer_police_verification')) {
                $table->string('employer_police_verification')->nullable()->after('employer_aadhar_back');
            }
            if (!Schema::hasColumn('users', 'employer_other_doc')) {
                $table->string('employer_other_doc')->nullable()->after('employer_police_verification');
            }
            if (!Schema::hasColumn('users', 'fir_document')) {
                $table->string('fir_document')->nullable()->after('employer_other_doc');
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
                'employer_aadhar_front',
                'employer_aadhar_back',
                'employer_police_verification',
                'employer_other_doc',
                'fir_document'
            ]);
        });
    }
};
