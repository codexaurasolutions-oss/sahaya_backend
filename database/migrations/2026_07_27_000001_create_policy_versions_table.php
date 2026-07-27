<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('policy_versions', function (Blueprint $table) {
            $table->id();
            $table->string('type');               // 'terms_and_conditions', 'privacy_policy', 'disclaimer'
            $table->string('version', 50);         // e.g. '1.0', '2.0'
            $table->string('title');               // Display title
            $table->text('content_hash');          // Hash of content for change detection
            $table->boolean('is_current')->default(true);
            $table->timestamps();
        });

        Schema::create('user_policy_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('policy_type');          // 'terms_and_conditions', 'privacy_policy', 'disclaimer'
            $table->string('policy_version', 50);  // version accepted
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('accepted_at');
            $table->timestamps();

            $table->unique(['user_id', 'policy_type'], 'unique_user_policy');
        });

        // Seed initial versions (current policies)
        DB::table('policy_versions')->insert([
            ['type' => 'terms_and_conditions', 'version' => '1.0', 'title' => 'Terms of Service', 'content_hash' => md5('terms_v1_feb2026'), 'is_current' => true, 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'privacy_policy', 'version' => '1.0', 'title' => 'Privacy Policy', 'content_hash' => md5('privacy_v1_feb2026'), 'is_current' => true, 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'disclaimer', 'version' => '1.0', 'title' => 'Disclaimer', 'content_hash' => md5('disclaimer_v1_feb2026'), 'is_current' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('user_policy_acceptances');
        Schema::dropIfExists('policy_versions');
    }
};
