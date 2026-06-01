<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_search_plans', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('project_id');
            $table->string('status', 24)->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->json('default_providers')->nullable();
            $table->unsignedSmallInteger('default_year_from')->nullable();
            $table->unsignedSmallInteger('default_year_to')->nullable();
            $table->unsignedSmallInteger('default_result_limit')->default(50);
            $table->boolean('include_raw_data')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->unique('project_id');
            $table->index(['project_id', 'status']);
        });

        Schema::create('project_search_plan_queries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('project_search_plan_id');
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->string('query_key', 80);
            $table->string('label', 180);
            $table->text('query');
            $table->json('providers')->nullable();
            $table->unsignedSmallInteger('year_from')->nullable();
            $table->unsignedSmallInteger('year_to')->nullable();
            $table->unsignedSmallInteger('result_limit')->default(50);
            $table->boolean('include_raw_data')->default(false);
            $table->timestamps();

            $table->foreign('project_search_plan_id')->references('id')->on('project_search_plans')->cascadeOnDelete();
            $table->unique(
                ['project_search_plan_id', 'query_key'],
                'pspq_unique'
            );
            $table->index(
                ['project_search_plan_id', 'sort_order'],
                'pspq_sort_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_search_plan_queries');
        Schema::dropIfExists('project_search_plans');
    }
};
