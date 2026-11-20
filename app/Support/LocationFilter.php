<?php

namespace App\Support;

/**
 * Optional location filters shared by every list endpoint:
 * country, state, province and city (plus region for schools).
 *
 * Matching is case-insensitive and exact (surrounding spaces ignored), so the
 * values are meant to come from a dropdown. A missing or empty parameter adds
 * no condition at all, which keeps every existing request behaving exactly as
 * before.
 */
class LocationFilter
{
    public const FIELDS = ['country', 'state', 'province', 'city'];

    /**
     * Validation rules for FormRequests that use ->validated().
     */
    public static function rules(): array
    {
        return array_fill_keys(self::FIELDS, ['nullable', 'string', 'max:100']);
    }

    /**
     * Same rules plus region (schools).
     */
    public static function schoolRules(): array
    {
        return self::rules() + ['region' => ['nullable', 'string', 'max:255']];
    }

    /**
     * Filter on the given columns of the query's own table
     * (users, admins, ...).
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     */
    public static function apply($query, $params, array $fields = self::FIELDS, ?string $table = null): void
    {
        foreach ($fields as $field) {
            $value = self::value($params, $field);
            if ($value === null) {
                continue;
            }

            $column = $table ? "{$table}.{$field}" : $field;
            $query->whereRaw("LOWER(TRIM({$column})) = ?", [$value]);
        }
    }

    /**
     * Students: state / province / city are the student's own; country is the
     * country of the student's school (students have no country column).
     */
    public static function applyToStudents($query, $params, ?string $table = 'students'): void
    {
        self::apply($query, $params, ['state', 'province', 'city'], $table);

        $country = self::value($params, 'country');
        if ($country !== null) {
            $query->whereIn(
                ($table ? "{$table}." : '') . 'institution_id',
                \App\Models\Institution::query()->select('id')->whereRaw('LOWER(TRIM(country)) = ?', [$country])
            );
        }
    }

    /**
     * Schools: country and region.
     */
    public static function applyToInstitutions($query, $params, ?string $table = null): void
    {
        self::apply($query, $params, ['country', 'region'], $table);
    }

    /**
     * Normalised value of one parameter, or null when absent/empty.
     */
    private static function value($params, string $field): ?string
    {
        $raw = $params instanceof \Illuminate\Http\Request ? $params->input($field) : ($params[$field] ?? null);

        if (!is_string($raw) && !is_numeric($raw)) {
            return null;
        }

        $value = mb_strtolower(trim((string) $raw));

        return $value === '' ? null : $value;
    }
}
