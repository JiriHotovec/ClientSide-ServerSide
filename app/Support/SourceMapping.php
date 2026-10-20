<?php

namespace App\Support;

/** Builds the pull mapping stored on a device from the simple form fields (one JSON path per dataset). */
class SourceMapping
{
    /**
     * @param  array<int|string, mixed>  $input  form input: map[<type id>], map_timestamp, map_items
     * @param  array<int, string>  $keysById  measurement type keys of the datasets ticked in the form, by id
     * @return array{values: array<string, string>, timestamp?: string, items_path?: string}|null
     */
    public static function fromForm(array $input, array $keysById): ?array
    {
        $values = [];
        foreach ((array) ($input['map'] ?? []) as $id => $path) {
            $path = is_string($path) ? trim($path) : '';
            if ($path !== '' && isset($keysById[(int) $id])) {
                $values[$keysById[(int) $id]] = $path;
            }
        }
        if ($values === []) {
            return null;
        }

        $mapping = ['values' => $values];
        foreach (['timestamp' => 'map_timestamp', 'items_path' => 'map_items'] as $key => $field) {
            $value = is_string($input[$field] ?? null) ? trim($input[$field]) : '';
            if ($value !== '') {
                $mapping[$key] = $value;
            }
        }

        return $mapping;
    }
}
