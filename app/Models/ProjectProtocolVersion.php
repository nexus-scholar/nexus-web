<?php

namespace App\Models;

use App\Enums\ProtocolStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectProtocolVersion extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'project_id',
        'project_protocol_id',
        'version',
        'status',
        'snapshot',
        'reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'status' => ProtocolStatus::class,
            'snapshot' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function protocol(): BelongsTo
    {
        return $this->belongsTo(ProjectProtocol::class, 'project_protocol_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
