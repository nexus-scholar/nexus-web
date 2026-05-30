<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_full_text_batches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('project_id');
            $table->uuid('screening_batch_id');
            $table->uuid('snapshot_id');
            $table->string('status', 32)->default('queued');
            $table->unsignedInteger('candidate_count')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('manual_needed_count')->default(0);
            $table->string('destination_folder');
            $table->json('source_policy')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('screening_batch_id')->references('id')->on('project_screening_batches')->restrictOnDelete();
            $table->foreign('snapshot_id')->references('id')->on('corpus_snapshots')->restrictOnDelete();
            $table->index(['project_id', 'status']);
            $table->index(['screening_batch_id', 'status']);
            $table->index(['project_id', 'created_at']);
        });

        Schema::create('project_full_text_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('project_id');
            $table->uuid('batch_id');
            $table->uuid('work_id');
            $table->string('screening_decision', 32);
            $table->string('status', 32)->default('queued');
            $table->string('source_alias', 64)->nullable();
            $table->string('artifact_type', 32)->nullable();
            $table->string('artifact_path')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('batch_id')->references('id')->on('project_full_text_batches')->cascadeOnDelete();
            $table->foreign('work_id')->references('id')->on('scholarly_works')->restrictOnDelete();
            $table->unique(['batch_id', 'work_id']);
            $table->index(['project_id', 'status']);
            $table->index(['work_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_full_text_items');
        Schema::dropIfExists('project_full_text_batches');
    }
};
