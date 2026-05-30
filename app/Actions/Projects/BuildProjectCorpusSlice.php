<?php

namespace App\Actions\Projects;

use App\Models\Project;
use Nexus\Search\Domain\Port\WorkRepositoryPort;
use Nexus\Shared\Domain\CorpusSlice;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;

class BuildProjectCorpusSlice
{
    public function __construct(
        private readonly ProjectCorpusMembershipHasher $membership,
        private readonly WorkRepositoryPort $works,
    ) {}

    public function handle(Project $project): CorpusSlice
    {
        $workIds = $this->membership->handle($project)['unique_work_ids'];

        if ($workIds === []) {
            return CorpusSlice::empty();
        }

        $loaded = $this->works->findManyByIds(array_map(
            fn (string $workId): WorkId => new WorkId(WorkIdNamespace::INTERNAL, $workId),
            $workIds,
        ));

        // Deduplication must inspect every draft member. fromWorks() would
        // premerge records that already share an authoritative external ID.
        return CorpusSlice::fromWorksUnsafe(...array_values($loaded));
    }
}
