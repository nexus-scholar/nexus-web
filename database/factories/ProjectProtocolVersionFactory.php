<?php

namespace Database\Factories;

use App\Enums\ProtocolStatus;
use App\Models\Project;
use App\Models\ProjectProtocol;
use App\Models\ProjectProtocolVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectProtocolVersion>
 */
class ProjectProtocolVersionFactory extends Factory
{
    protected $model = ProjectProtocolVersion::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'project_protocol_id' => ProjectProtocol::factory(),
            'version' => 1,
            'status' => ProtocolStatus::Draft,
            'snapshot' => [],
            'reason' => null,
            'created_by' => User::factory(),
        ];
    }
}
