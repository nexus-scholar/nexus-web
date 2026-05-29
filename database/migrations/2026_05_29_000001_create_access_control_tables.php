<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type', 24);
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('suspended_at')->nullable();
            $table->foreignId('suspended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('suspended_reason')->nullable();
            $table->timestamps();

            $table->index('owner_user_id');
            $table->index('type');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->uuid('current_workspace_id')->nullable();
            $table->boolean('is_operator')->default(false);
            $table->timestamp('disabled_at')->nullable();
            $table->foreignId('disabled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('disabled_reason')->nullable();

            $table->foreign('current_workspace_id')->references('id')->on('workspaces')->nullOnDelete();
        });

        Schema::create('oauth_identities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 64);
            $table->string('provider_user_id');
            $table->string('email')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_user_id']);
            $table->index('user_id');
        });

        Schema::create('workspace_memberships', function (Blueprint $table): void {
            $table->id();
            $table->uuid('workspace_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 24);
            $table->string('status', 24);
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->unique(['workspace_id', 'user_id']);
            $table->index(['workspace_id', 'role']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('workspace_invitations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->string('email');
            $table->string('role', 24);
            $table->string('token_hash')->unique();
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->index(['workspace_id', 'email']);
        });

        Schema::create('audit_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id')->nullable();
            $table->uuid('project_id')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 96);
            $table->string('target_type', 96);
            $table->string('target_id');
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->nullOnDelete();
            $table->index(['workspace_id', 'event_type']);
            $table->index(['actor_user_id', 'occurred_at']);
            $table->index('target_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('workspace_invitations');
        Schema::dropIfExists('workspace_memberships');
        Schema::dropIfExists('oauth_identities');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['current_workspace_id']);
            $table->dropConstrainedForeignId('disabled_by');
            $table->dropColumn([
                'current_workspace_id',
                'is_operator',
                'disabled_at',
                'disabled_reason',
            ]);
        });

        Schema::dropIfExists('workspaces');
    }
};
