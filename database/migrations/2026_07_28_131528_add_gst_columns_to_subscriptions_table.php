<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->decimal('base_price', 10, 2)->default(0)->after('price');
            $table->decimal('gst_rate', 5, 2)->default(18.00)->after('base_price');
            $table->decimal('gst_amount', 10, 2)->default(0)->after('gst_rate');
        });
    }

    public function down()
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['base_price', 'gst_rate', 'gst_amount']);
        });
    }
};
