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
            if (!Schema::hasColumn('users', 'is_available')) {
                $table->boolean('is_available')->default(1)->after('is_active');
            }
            if (!Schema::hasColumn('users', 'is_job_seeking')) {
                $table->boolean('is_job_seeking')->default(1)->after('is_available');
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
            $table->dropColumn(['is_available', 'is_job_seeking']);
        });
    }
};
