<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('base_amount', 10, 2)->default(0)->after('amount');
            $table->decimal('gst_amount', 10, 2)->default(0)->after('base_amount');
            $table->decimal('total_amount', 10, 2)->default(0)->after('gst_amount');
        });
    }

    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['base_amount', 'gst_amount', 'total_amount']);
        });
    }
};
