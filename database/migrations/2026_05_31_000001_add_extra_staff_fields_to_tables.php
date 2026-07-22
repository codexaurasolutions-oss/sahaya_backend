<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('subscriptions')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                if (!Schema::hasColumn('subscriptions', 'extra_staff_price')) {
                    $table->decimal('extra_staff_price', 10, 2)->default(0.00)->after('extra_job_price');
                }
            });
        }

        if (Schema::hasTable('subscription_users')) {
            Schema::table('subscription_users', function (Blueprint $table) {
                if (!Schema::hasColumn('subscription_users', 'extra_staff')) {
                    $table->integer('extra_staff')->default(0)->after('extra_jobs');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('subscriptions')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                if (Schema::hasColumn('subscriptions', 'extra_staff_price')) {
                    $table->dropColumn('extra_staff_price');
                }
            });
        }

        if (Schema::hasTable('subscription_users')) {
            Schema::table('subscription_users', function (Blueprint $table) {
                if (Schema::hasColumn('subscription_users', 'extra_staff')) {
                    $table->dropColumn('extra_staff');
                }
            });
        }
    }
};
