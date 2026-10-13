<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_datasets', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->string('name');
            $table->string('version');
            $table->boolean('is_synthetic');
            $table->json('metadata')->nullable();
            $table->string('source_path');
            $table->timestamp('loaded_at')->nullable();
            $table->timestamps();

            $table->unique(['key', 'version']);
        });

        Schema::create('performance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_id')->constrained('performance_datasets')->cascadeOnDelete();
            $table->string('post_id');
            $table->date('published_date')->nullable();
            $table->string('platform');
            $table->string('content_format');
            $table->string('post_theme')->nullable();
            $table->text('caption_excerpt')->nullable();
            $table->string('campaign_goal');
            // Nullable metrics mean "N/A"; the analyser excludes such records.
            $table->unsignedInteger('impressions')->nullable();
            $table->unsignedInteger('reach')->nullable();
            $table->unsignedInteger('likes')->nullable();
            $table->unsignedInteger('comments')->nullable();
            $table->unsignedInteger('shares')->nullable();
            $table->unsignedInteger('link_clicks')->nullable();
            $table->unsignedInteger('saves')->nullable();
            $table->string('data_source');
            $table->boolean('is_real_performance_data');
            $table->timestamps();

            $table->unique(['dataset_id', 'post_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_records');
        Schema::dropIfExists('performance_datasets');
    }
};
