<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_search_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('project_id');
            $table->uuid('project_search_plan_id');
            $table->string('status', 24)->default('queued');
            $table->unsignedInteger('plan_version');
            $table->unsignedSmallInteger('query_count')->default(0);
            $table->unsignedSmallInteger('failure_count')->default(0);
            $table->unsignedInteger('total_raw')->default(0);
            $table->unsignedInteger('total_unique')->default(0);
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('project_search_plan_id')->references('id')->on('project_search_plans')->cascadeOnDelete();
            $table->index(['project_id', 'status']);
            $table->index(['project_search_plan_id', 'created_at']);
        });

        Schema::create('project_search_run_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('project_search_run_id');
            $table->uuid('project_search_plan_query_id')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->string('query_key', 80);
            $table->string('label', 180);
            $table->text('query');
            $table->json('providers')->nullable();
            $table->unsignedSmallInteger('year_from')->nullable();
            $table->unsignedSmallInteger('year_to')->nullable();
            $table->unsignedSmallInteger('result_limit')->default(50);
            $table->boolean('include_raw_data')->default(false);
            $table->string('status', 24)->default('queued');
            $table->string('core_search_query_id')->nullable();
            $table->unsignedInteger('total_raw')->default(0);
            $table->unsignedInteger('total_unique')->default(0);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->foreign('project_search_run_id')->references('id')->on('project_search_runs')->cascadeOnDelete();
            $table->foreign('project_search_plan_query_id')->references('id')->on('project_search_plan_queries')->nullOnDelete();
            $table->unique(['project_search_run_id', 'query_key']);
            $table->index(['project_search_run_id', 'status']);
            $table->index('core_search_query_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_search_run_items');
        Schema::dropIfExists('project_search_runs');
    }
};
