<?php

namespace App\Support;

/** Interface strings used by public/js/*.js (sent to the browser as JSON in the layout). */
class JsStrings
{
    public const KEYS = [
        'Loading measurements…',
        'Please log in again.',
        'You are not allowed to view this device.',
        'This dataset or device is not available.',
        'The server could not deliver the data right now. Please try again later.',
        'Could not reach the server. Check your connection and try again.',
        'The external data source is currently unavailable: :message Showing the last stored measurements.',
        'No measurements of :name in the selected period. Missing data is not shown as zero.',
        'No valid values in this period.',
        'Missing value',
        'Choose both dates and times.',
        'The start must be before the end.',
        'Choose a range of at most 92 days.',
        'Enlarged chart of :name. Open the data table for exact values.',
        'Lowest',
        'Average',
        'Highest',
        'Value',
        'Location',
        'Copied.',
        'Select at least one dataset above first.',
        'Searching…',
        'No places found.',
        'Pin placed. Adjust it if needed and confirm.',
        'The place search failed. Try again later.',
        'Choose a place on the map first.',
        'Enlarge',
        'Shrink',
        'You are not allowed to do this.',
        'This item no longer exists.',
        'Your session expired. Reload the page and try again.',
        'Too many attempts. Wait a minute and try again.',
        'Something went wrong on the server. Please try again.',
    ];

    public static function all(): array
    {
        return collect(self::KEYS)->mapWithKeys(fn ($k) => [$k => __($k)])->all();
    }
}
