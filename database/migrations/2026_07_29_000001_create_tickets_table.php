<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('zoho_ticket_id')->nullable();
            $table->string('subject');
            $table->text('description')->nullable();
            $table->string('category')->nullable()->comment('Payment, Staff, App Bug, General');
            $table->string('priority')->default('Medium')->comment('Low, Medium, High, Urgent');
            $table->string('status')->default('Open')->comment('Open, In Progress, Resolved, Closed');
            $table->string('image')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('zoho_ticket_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
