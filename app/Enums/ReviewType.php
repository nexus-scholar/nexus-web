<?php

namespace App\Enums;

enum ReviewType: string
{
    case SystematicReview = 'systematic_review';
    case ScopingReview = 'scoping_review';
    case ThesisReview = 'thesis_review';
    case LivingReview = 'living_review';
    case EvidenceMap = 'evidence_map';

    public function label(): string
    {
        return match ($this) {
            self::SystematicReview => 'Systematic review',
            self::ScopingReview => 'Scoping review',
            self::ThesisReview => 'Thesis review',
            self::LivingReview => 'Living review',
            self::EvidenceMap => 'Evidence map',
        };
    }
}
