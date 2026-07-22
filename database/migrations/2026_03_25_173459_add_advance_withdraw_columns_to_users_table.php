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
            // Amount column
            $table->decimal('advance_withdraw_amount', 12, 2)
                  ->default(0)
                  ->after('remember_token');

            // Who added (user / ai)
            $table->string('advance_withdraw_added_by')
                  ->nullable()
                  ->after('advance_withdraw_amount');

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
                'advance_withdraw_amount',
                'advance_withdraw_added_by'
            ]);
        });
    }
};
