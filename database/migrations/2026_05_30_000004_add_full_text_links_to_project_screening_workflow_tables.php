<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_screening_batches', function (Blueprint $table): void {
            $table->uuid('source_full_text_batch_id')->nullable()->after('snapshot_id');

            $table->foreign('source_full_text_batch_id')
                ->references('id')
                ->on('project_full_text_batches')
                ->nullOnDelete();
            $table->index(['project_id', 'stage', 'source_full_text_batch_id'], 'psb_project_stage_full_text_batch_idx');
        });

        Schema::table('project_screening_assignments', function (Blueprint $table): void {
            $table->uuid('source_full_text_item_id')->nullable()->after('screening_decision_id');

            $table->foreign('source_full_text_item_id')
                ->references('id')
                ->on('project_full_text_items')
                ->nullOnDelete();
            $table->index(['batch_id', 'source_full_text_item_id'], 'psa_batch_full_text_item_idx');
        });
    }

    public function down(): void
    {
        Schema::table('project_screening_assignments', function (Blueprint $table): void {
            $table->dropForeign(['source_full_text_item_id']);
            $table->dropIndex('psa_batch_full_text_item_idx');
            $table->dropColumn('source_full_text_item_id');
        });

        Schema::table('project_screening_batches', function (Blueprint $table): void {
            $table->dropForeign(['source_full_text_batch_id']);
            $table->dropIndex('psb_project_stage_full_text_batch_idx');
            $table->dropColumn('source_full_text_batch_id');
        });
    }
};
