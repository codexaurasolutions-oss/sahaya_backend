<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStayTypeToTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_work_infos', function (Blueprint $table) {
            if (!Schema::hasColumn('user_work_infos', 'stay_type')) {
                $table->string('stay_type')->nullable()->after('working_days');
            }
        });

        Schema::table('jobs', function (Blueprint $table) {
            if (!Schema::hasColumn('jobs', 'stay_type')) {
                $table->string('stay_type')->nullable()->after('status');
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
        Schema::table('user_work_infos', function (Blueprint $table) {
            if (Schema::hasColumn('user_work_infos', 'stay_type')) {
                $table->dropColumn('stay_type');
            }
        });

        Schema::table('jobs', function (Blueprint $table) {
            if (Schema::hasColumn('jobs', 'stay_type')) {
                $table->dropColumn('stay_type');
            }
        });
    }
}
