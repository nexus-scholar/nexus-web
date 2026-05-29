<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case Draft = 'draft';
    case ReadyForSearch = 'ready_for_search';
    case Searching = 'searching';
    case DraftCorpus = 'draft_corpus';
    case Locked = 'locked';
    case LockedCorpus = 'locked_corpus';
    case Screening = 'screening';
    case Adjudication = 'adjudication';
    case Exporting = 'exporting';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::ReadyForSearch => 'Ready for search',
            self::Searching => 'Searching',
            self::DraftCorpus => 'Draft corpus',
            self::Locked => 'Locked corpus',
            self::LockedCorpus => 'Locked corpus',
            self::Screening => 'Screening',
            self::Adjudication => 'Adjudication',
            self::Exporting => 'Exporting',
            self::Archived => 'Archived',
        };
    }

    public function isActive(): bool
    {
        return $this !== self::Archived;
    }
}
