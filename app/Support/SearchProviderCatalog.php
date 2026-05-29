<?php

namespace App\Support;

class SearchProviderCatalog
{
    public const ALIASES = [
        'openalex',
        'crossref',
        'semantic_scholar',
        'arxiv',
        'pubmed',
        'doaj',
        'ieee',
    ];

    /**
     * @return list<string>
     */
    public static function fromText(string $value): array
    {
        return collect(explode(',', $value))
            ->map(fn (string $provider): string => self::normalize($provider))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public static function normalize(string $provider): string
    {
        return str_replace('-', '_', strtolower(trim($provider)));
    }

    /**
     * @param  list<string>  $providers
     * @return list<string>
     */
    public static function unsupported(array $providers): array
    {
        return collect($providers)
            ->map(fn (string $provider): string => self::normalize($provider))
            ->filter()
            ->reject(fn (string $provider): bool => in_array($provider, self::ALIASES, true))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public static function unsupportedFromText(string $value): array
    {
        return self::unsupported(self::fromText($value));
    }
}
