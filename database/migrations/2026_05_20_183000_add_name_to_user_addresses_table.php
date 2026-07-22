<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNameToUserAddressesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('user_addresses')) {
            Schema::table('user_addresses', function (Blueprint $table) {
                if (!Schema::hasColumn('user_addresses', 'name')) {
                    $table->string('name')->nullable()->after('user_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('user_addresses')) {
            Schema::table('user_addresses', function (Blueprint $table) {
                if (Schema::hasColumn('user_addresses', 'name')) {
                    $table->dropColumn('name');
                }
            });
        }
    }
}
