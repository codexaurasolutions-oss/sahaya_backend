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
        Schema::table('job_apply_limit_purchases', function (Blueprint $table) {
            $table->decimal('base_amount', 10, 2)->default(0)->after('amount');
            $table->decimal('gst_amount', 10, 2)->default(0)->after('base_amount');
            $table->decimal('total_amount', 10, 2)->default(0)->after('gst_amount');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('job_apply_limit_purchases', function (Blueprint $table) {
            $table->dropColumn(['base_amount', 'gst_amount', 'total_amount']);
        });
    }
};
