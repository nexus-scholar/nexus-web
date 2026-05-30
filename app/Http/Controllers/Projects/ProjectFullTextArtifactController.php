<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectFullTextItem;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectFullTextArtifactController extends Controller
{
    public function show(Project $project, ProjectFullTextItem $item): StreamedResponse
    {
        abort_unless((string) $item->project_id === (string) $project->id, 404);
        $this->authorize('downloadFullTextArtifact', $project);

        abort_unless($item->status->value === 'success' && filled($item->artifact_path), 404);

        $disk = Storage::disk((string) config('nexus.dissemination.pdf_storage_disk', 'public'));
        abort_unless($disk->exists((string) $item->artifact_path), 404);

        return $disk->download((string) $item->artifact_path);
    }
}
