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
        Schema::create('seo_pages', function (Blueprint $table) {
            $table->id();
            $table->string('page_id');
            $table->string('page_name');
            $table->string('title');
            $table->longText('meta_description');
            $table->string('meta_keywords');
            $table->string('twitter_card');
            $table->string('twitter_site');
            $table->string('og_url');
            $table->string('og_type');
            $table->string('og_title');
            $table->longText('og_description');
            $table->longText('meta_chronicles');
            $table->longText('og_image');
            $table->integer('is_deleted')->default('0');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('seo_pages');
    }
};
