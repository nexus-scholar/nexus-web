<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_corpus_dedup_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('project_id');
            $table->foreignId('ran_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 24)->default('completed');
            $table->char('membership_hash', 64);
            $table->unsignedInteger('input_count')->default(0);
            $table->unsignedInteger('representative_count')->default(0);
            $table->unsignedInteger('duplicate_cluster_count')->default(0);
            $table->unsignedInteger('duplicate_member_count')->default(0);
            $table->unsignedInteger('duplicates_removed')->default(0);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->json('policy_stats')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->index(['project_id', 'created_at']);
            $table->index(['project_id', 'membership_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_corpus_dedup_runs');
    }
};
