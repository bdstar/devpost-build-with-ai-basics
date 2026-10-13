<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('website_url');
            $table->json('social_urls')->nullable();
            // {key: {value, provenance, source_url?, retrieved_at?}}
            $table->json('fields');
            // {pages: [...], skipped: [...], retrieved_at, error?}
            $table->json('sources')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('confirmed_at')->nullable();
            $table->string('ai_provenance')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_profiles');
    }
};
