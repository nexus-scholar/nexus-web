<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            if (! Schema::hasColumn('projects', 'workspace_id')) {
                $table->uuid('workspace_id')->nullable()->after('id');
            }

            if (! Schema::hasColumn('projects', 'owner_user_id')) {
                $table->foreignId('owner_user_id')->nullable()->after('workspace_id')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('projects', 'slug')) {
                $table->string('slug')->nullable()->after('name');
            }

            if (! Schema::hasColumn('projects', 'review_type')) {
                $table->string('review_type', 48)->nullable()->after('description');
            }

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->unique(['workspace_id', 'slug']);
            $table->index(['workspace_id', 'status']);
            $table->index('owner_user_id');
        });

        Schema::create('project_memberships', function (Blueprint $table): void {
            $table->id();
            $table->uuid('project_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 24);
            $table->string('status', 24);
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->unique(['project_id', 'user_id']);
            $table->index(['project_id', 'role']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('project_protocols', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('project_id');
            $table->string('status', 24)->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->string('title');
            $table->text('research_question')->nullable();
            $table->text('background')->nullable();
            $table->text('inclusion_criteria')->nullable();
            $table->text('exclusion_criteria')->nullable();
            $table->json('target_providers')->nullable();
            $table->date('date_range_start')->nullable();
            $table->date('date_range_end')->nullable();
            $table->boolean('no_date_limit')->default(false);
            $table->string('language_policy')->nullable();
            $table->unsignedTinyInteger('min_reviewer_count')->default(2);
            $table->string('ai_screening_policy', 48)->default('human_only');
            $table->string('full_text_policy', 48)->default('optional');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->unique('project_id');
            $table->index(['project_id', 'status']);
        });

        Schema::create('project_protocol_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('project_id');
            $table->uuid('project_protocol_id');
            $table->unsignedInteger('version');
            $table->string('status', 24);
            $table->json('snapshot');
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('project_protocol_id')->references('id')->on('project_protocols')->cascadeOnDelete();
            $table->unique(['project_protocol_id', 'version']);
            $table->index(['project_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_protocol_versions');
        Schema::dropIfExists('project_protocols');
        Schema::dropIfExists('project_memberships');

        Schema::table('projects', function (Blueprint $table): void {
            $table->dropUnique(['workspace_id', 'slug']);
            $table->dropIndex(['workspace_id', 'status']);
            $table->dropIndex(['owner_user_id']);
            $table->dropForeign(['workspace_id']);
            $table->dropConstrainedForeignId('owner_user_id');
            $table->dropColumn(['workspace_id', 'slug', 'review_type']);
        });
    }
};
