<?php

namespace App\Support;

use Illuminate\Support\MessageBag;

/** The tabs of the device form and which tab a validation error belongs to. Labels are translation keys. */
class DeviceFormTabs
{
    public const TABS = ['basics' => 'Basics', 'datasets' => 'Datasets', 'charts' => 'Charts', 'source' => 'Data source'];

    public static function tabFor(string $field): string
    {
        return match (true) {
            str_starts_with($field, 'types'), str_starts_with($field, 'ts'), str_starts_with($field, 'type_settings') => 'datasets',
            str_starts_with($field, 'charts') => 'charts',
            str_starts_with($field, 'source_'), str_starts_with($field, 'map'), $field === 'fetch_interval_min' => 'source',
            default => 'basics',
        };
    }

    /** @return array<string, int> number of errors per tab (only tabs with errors) */
    public static function errorCounts(MessageBag $errors): array
    {
        $counts = [];
        foreach (array_keys($errors->messages()) as $field) {
            $tab = self::tabFor($field);
            $counts[$tab] = ($counts[$tab] ?? 0) + count($errors->get($field));
        }

        return array_intersect_key($counts + array_fill_keys(array_keys(self::TABS), 0), $counts);
    }
}
