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
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->integer('staff_limit')->default(2)->after('job_limit');
        });

        Schema::table('subscription_users', function (Blueprint $table) {
            $table->integer('staff_user_limit')->default(2)->after('job_user_limit');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('staff_limit');
        });

        Schema::table('subscription_users', function (Blueprint $table) {
            $table->dropColumn('staff_user_limit');
        });
    }
};
