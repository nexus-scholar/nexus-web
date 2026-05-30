<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_screening_batches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('project_id');
            $table->uuid('screening_run_id')->nullable();
            $table->string('stage', 64)->default('title_abstract');
            $table->string('status', 32)->default('active');
            $table->unsignedTinyInteger('required_reviewer_count')->default(2);
            $table->char('criteria_hash', 64);
            $table->uuid('snapshot_id')->nullable();
            $table->json('assignment_policy')->nullable();
            $table->json('counts')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('screening_run_id')->references('id')->on('screening_runs')->nullOnDelete();
            $table->foreign('snapshot_id')->references('id')->on('corpus_snapshots')->nullOnDelete();
            $table->index(['project_id', 'stage', 'status']);
            $table->index(['snapshot_id', 'stage']);
        });

        Schema::create('project_screening_assignments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('project_id');
            $table->uuid('batch_id');
            $table->uuid('work_id');
            $table->foreignId('assigned_to')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('stage', 64)->default('title_abstract');
            $table->string('status', 32)->default('pending');
            $table->uuid('screening_decision_id')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('batch_id')->references('id')->on('project_screening_batches')->cascadeOnDelete();
            $table->foreign('work_id')->references('id')->on('scholarly_works')->restrictOnDelete();
            $table->foreign('screening_decision_id')->references('id')->on('screening_decisions')->nullOnDelete();
            $table->unique(['batch_id', 'work_id', 'assigned_to']);
            $table->index(['project_id', 'stage', 'status']);
            $table->index(['batch_id', 'assigned_to', 'status']);
            $table->index(['batch_id', 'work_id']);
            $table->index(['assigned_to', 'status']);
        });

        Schema::create('project_screening_conflicts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('project_id');
            $table->uuid('batch_id');
            $table->uuid('work_id');
            $table->string('stage', 64)->default('title_abstract');
            $table->string('status', 32)->default('open');
            $table->json('decision_ids');
            $table->uuid('resolved_decision_id')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_reason')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('batch_id')->references('id')->on('project_screening_batches')->cascadeOnDelete();
            $table->foreign('work_id')->references('id')->on('scholarly_works')->restrictOnDelete();
            $table->foreign('resolved_decision_id')->references('id')->on('screening_decisions')->nullOnDelete();
            $table->unique(['batch_id', 'work_id', 'stage']);
            $table->index(['project_id', 'stage', 'status']);
            $table->index(['batch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_screening_conflicts');
        Schema::dropIfExists('project_screening_assignments');
        Schema::dropIfExists('project_screening_batches');
    }
};
