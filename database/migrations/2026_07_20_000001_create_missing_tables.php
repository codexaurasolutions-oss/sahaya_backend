<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. promo_codes (referenced by bookings)
        if (!Schema::hasTable('promo_codes')) {
            Schema::create('promo_codes', function (Blueprint $table) {
                $table->id();
                $table->string('promo_code')->unique();
                $table->string('type')->default('percentage');
                $table->decimal('amount', 8, 2)->default(0);
                $table->date('start_on');
                $table->date('expired_on');
                $table->text('description')->nullable();
                $table->boolean('isActive')->default(true);
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('extra_text')->nullable();
                $table->boolean('is_highlighted')->default(false);
                $table->timestamps();
            });
        }

        // 2. services (referenced by bookings, reviews, carts)
        if (!Schema::hasTable('services')) {
            Schema::create('services', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('name');
                $table->text('description')->nullable();
                $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
                $table->decimal('price', 10, 2)->default(0);
                $table->string('gender')->default('unisex');
                $table->string('duration')->default('60');
                $table->json('peak_hours')->nullable();
                $table->json('available_schedule')->nullable();
                $table->json('addons')->nullable();
                $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
                $table->string('image')->nullable();
                $table->timestamps();
            });
        }

        // 3. sub_services
        if (!Schema::hasTable('sub_services')) {
            Schema::create('sub_services', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
                $table->string('name');
                $table->string('image')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // 4. bookings
        if (!Schema::hasTable('bookings')) {
            Schema::create('bookings', function (Blueprint $table) {
                $table->id();
                $table->string('order_id')->nullable();
                $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('vendor_id')->constrained('users')->cascadeOnDelete();
                $table->json('schedule_time')->nullable();
                $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
                $table->foreignId('promo_code_id')->nullable()->constrained('promo_codes')->nullOnDelete();
                $table->decimal('amount', 10, 2)->default(0);
                $table->decimal('tax', 10, 2)->default(0);
                $table->decimal('platform_fee', 10, 2)->default(0);
                $table->string('status')->default('pending');
                $table->datetime('reschedule_time')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->boolean('is_paid_key')->default(false);
                $table->string('payment_id')->nullable();
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }

        // 5. reviews
        if (!Schema::hasTable('reviews')) {
            Schema::create('reviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
                $table->unsignedBigInteger('given_by_id');
                $table->string('given_by_type');
                $table->unsignedBigInteger('received_by_id');
                $table->string('received_by_type');
                $table->integer('rating')->default(5);
                $table->text('review')->nullable();
                $table->timestamps();
            });
        }

        // 6. wallet
        if (!Schema::hasTable('wallet')) {
            Schema::create('wallet', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->decimal('amount', 10, 2)->default(0);
                $table->string('type')->default('credit');
                $table->string('transaction_id')->nullable();
                $table->string('payment_id')->nullable();
                $table->string('status')->default('0');
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // 7. bank_accounts
        if (!Schema::hasTable('bank_accounts')) {
            Schema::create('bank_accounts', function (Blueprint $table) {
                $table->id();
                $table->string('bank_name');
                $table->string('account_number');
                $table->string('ifsc_code');
                $table->string('bank_type')->default('saving');
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->boolean('is_set')->default(false);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // 8. faq_support
        if (!Schema::hasTable('faq_support')) {
            Schema::create('faq_support', function (Blueprint $table) {
                $table->id();
                $table->string('category');
                $table->text('question');
                $table->text('answer')->nullable();
                $table->string('role')->nullable();
                $table->boolean('is_active')->default(true);
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        // 9. mail_shortcuts
        if (!Schema::hasTable('mail_shortcuts')) {
            Schema::create('mail_shortcuts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('subject');
                $table->text('body')->nullable();
                $table->boolean('is_all_users')->default(false);
                $table->json('user_ids')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // 10. carts
        if (!Schema::hasTable('carts')) {
            Schema::create('carts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
                $table->decimal('price', 10, 2)->default(0);
                $table->json('addons')->nullable();
                $table->timestamps();
            });
        }

        // 11. wishlists
        if (!Schema::hasTable('wishlists')) {
            Schema::create('wishlists', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedBigInteger('vendorId');
                $table->timestamps();
            });
        }

        // 12. banner
        if (!Schema::hasTable('banner')) {
            Schema::create('banner', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('position')->nullable();
                $table->string('image');
                $table->string('type')->nullable();
                $table->string('extensions')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // 13. analytics
        if (!Schema::hasTable('analytics')) {
            Schema::create('analytics', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->decimal('spend_this_month', 10, 2)->default(0);
                $table->decimal('saved_this_month', 10, 2)->default(0);
                $table->integer('total_bookings')->default(0);
                $table->integer('favorite_providers')->default(0);
                $table->decimal('cashback_earned', 10, 2)->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics');
        Schema::dropIfExists('banner');
        Schema::dropIfExists('wishlists');
        Schema::dropIfExists('carts');
        Schema::dropIfExists('mail_shortcuts');
        Schema::dropIfExists('faq_support');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('wallet');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('sub_services');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('services');
        Schema::dropIfExists('promo_codes');
    }
};
