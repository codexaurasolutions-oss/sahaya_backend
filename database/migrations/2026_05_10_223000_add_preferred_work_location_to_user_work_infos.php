<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPreferredWorkLocationToUserWorkInfos extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_work_infos', function (Blueprint $table) {
            $table->string('preferred_work_location')->nullable()->after('primary_role');
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
            $table->dropColumn('preferred_work_location');
        });
    }
}
