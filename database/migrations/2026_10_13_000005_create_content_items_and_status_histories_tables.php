<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('idea_id')->constrained('content_ideas')->cascadeOnDelete();
            $table->text('caption');
            $table->text('hashtags')->nullable();
            $table->string('cta')->nullable();
            $table->text('alt_text')->nullable();
            $table->string('image_path')->nullable();
            $table->string('image_provenance')->nullable();
            $table->json('video_brief')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('due_recorded_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('publish_method')->nullable();
            $table->text('feedback')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('provenance');
            $table->timestamps();
        });

        Schema::create('status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_item_id')->constrained('content_items')->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('note')->nullable();
            $table->string('actor');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_histories');
        Schema::dropIfExists('content_items');
    }
};
