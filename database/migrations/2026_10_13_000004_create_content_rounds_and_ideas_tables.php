<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_rounds', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('number');
            $table->string('mode');
            $table->json('profile_snapshot');
            $table->json('knowledge_record_ids')->nullable();
            $table->timestamps();
        });

        Schema::create('content_ideas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('round_id')->constrained('content_rounds')->cascadeOnDelete();
            $table->string('fixture_key')->nullable();
            $table->string('title');
            $table->string('topic')->nullable();
            $table->string('platform');
            $table->string('format');
            $table->string('campaign_goal');
            $table->text('audience')->nullable();
            $table->text('objective')->nullable();
            $table->string('cta')->nullable();
            $table->text('hook')->nullable();
            $table->text('key_message')->nullable();
            $table->unsignedSmallInteger('duration_s')->nullable();
            $table->string('aspect_ratio')->nullable();
            $table->text('why')->nullable();
            $table->json('influenced_by')->nullable();
            $table->boolean('knowledge_driven')->default(false);
            $table->boolean('selected')->default(false);
            $table->string('provenance');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_ideas');
        Schema::dropIfExists('content_rounds');
    }
};
