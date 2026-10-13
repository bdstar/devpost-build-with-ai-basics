<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_records', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->string('category');
            $table->text('observation');
            $table->text('recommendation')->nullable();
            $table->string('dimension');
            $table->string('group_value');
            $table->json('supporting_post_ids');
            $table->unsignedInteger('sample_size');
            $table->unsignedInteger('excluded_count');
            $table->json('metrics');
            $table->text('formula');
            $table->float('cv')->nullable();
            $table->string('confidence');
            $table->text('rationale');
            $table->string('evidence_type');
            $table->string('source_dataset');
            $table->string('dataset_version');
            $table->timestamp('analysed_at');
            $table->timestamps();

            $table->unique(['key', 'source_dataset', 'dataset_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_records');
    }
};
