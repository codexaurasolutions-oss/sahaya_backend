<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLocationFieldsToUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('location')->nullable()->after('phone_number');
            $table->string('lat')->nullable()->after('location');
            $table->string('long')->nullable()->after('lat');
            $table->string('country_code')->nullable()->after('phone_number_country_code');
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
            $table->dropColumn(['location', 'lat', 'long', 'country_code']);
        });
    }
}
