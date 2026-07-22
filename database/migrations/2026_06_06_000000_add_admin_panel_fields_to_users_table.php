<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin_panel_user')->default(false)->after('is_deleted');
            $table->unsignedBigInteger('admin_parent_id')->nullable()->after('is_admin_panel_user');
            $table->json('admin_permissions')->nullable()->after('admin_parent_id');

            $table
                ->foreign('admin_parent_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['admin_parent_id']);
            $table->dropColumn(['is_admin_panel_user', 'admin_parent_id', 'admin_permissions']);
        });
    }
};
