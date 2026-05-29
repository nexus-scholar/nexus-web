<?php

namespace App\Queries\Projects;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final readonly class CorpusFilters
{
    public function __construct(
        public ?string $search,
        public ?string $provider,
        public ?string $searchQueryId,
        public ?int $yearFrom,
        public ?int $yearTo,
        public ?string $identifier,
        public bool $missingAbstract,
        public bool $missingIdentifier,
        public bool $retracted,
        public string $duplicateStatus,
        public ?string $work,
        public int $perPage,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $maxYear = now()->year + 1;

        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'provider' => ['nullable', 'string', 'max:64'],
            'search_query' => ['nullable', 'string', 'max:80'],
            'year_from' => ['nullable', 'integer', 'min:1800', "max:{$maxYear}"],
            'year_to' => ['nullable', 'integer', 'min:1800', "max:{$maxYear}"],
            'identifier' => ['nullable', 'string', 'max:32'],
            'missing_abstract' => ['nullable', 'boolean'],
            'missing_identifier' => ['nullable', 'boolean'],
            'retracted' => ['nullable', 'boolean'],
            'duplicate_status' => ['nullable', Rule::in(['all', 'in_cluster', 'not_clustered'])],
            'work' => ['nullable', 'string', 'max:80'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $yearFrom = self::nullableInteger($data['year_from'] ?? null);
        $yearTo = self::nullableInteger($data['year_to'] ?? null);

        if ($yearFrom !== null && $yearTo !== null && $yearTo < $yearFrom) {
            abort(422, 'The end year must be greater than or equal to the start year.');
        }

        return new self(
            search: self::nullableString($data['q'] ?? null),
            provider: self::nullableString($data['provider'] ?? null),
            searchQueryId: self::nullableString($data['search_query'] ?? null),
            yearFrom: $yearFrom,
            yearTo: $yearTo,
            identifier: self::nullableString($data['identifier'] ?? null),
            missingAbstract: $request->boolean('missing_abstract'),
            missingIdentifier: $request->boolean('missing_identifier'),
            retracted: $request->boolean('retracted'),
            duplicateStatus: (string) ($data['duplicate_status'] ?? 'all'),
            work: self::nullableString($data['work'] ?? null),
            perPage: (int) ($data['per_page'] ?? 10),
        );
    }

    /**
     * @return array<string, bool|int|string|null>
     */
    public function toArray(): array
    {
        return [
            'q' => $this->search,
            'provider' => $this->provider,
            'search_query' => $this->searchQueryId,
            'year_from' => $this->yearFrom,
            'year_to' => $this->yearTo,
            'identifier' => $this->identifier,
            'missing_abstract' => $this->missingAbstract,
            'missing_identifier' => $this->missingIdentifier,
            'retracted' => $this->retracted,
            'duplicate_status' => $this->duplicateStatus,
            'work' => $this->work,
            'per_page' => $this->perPage,
        ];
    }

    /**
     * @return array<string, bool|int|string>
     */
    public function queryParameters(array $overrides = []): array
    {
        return collect([...$this->toArray(), ...$overrides])
            ->reject(fn (mixed $value, string $key): bool => $value === null
                || $value === ''
                || ($value === false && in_array($key, ['missing_abstract', 'missing_identifier', 'retracted'], true))
                || ($key === 'duplicate_status' && $value === 'all')
                || ($key === 'per_page' && $value === 10))
            ->all();
    }

    private static function nullableInteger(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
