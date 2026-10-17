<?php

namespace App\Support;

use App\Models\MeasurementType;

/**
 * Built-in measurement types: weather, air quality, water and soil, electricity and solar power, and more.
 * They are global (visible to everybody) and translated by key in lang/{locale}/measurement.php.
 * Names are stored in English; sync() only adds what is missing, so edits made by administrators are kept.
 */
class SystemTypes
{
    /** key => [name, unit, category, decimals, color, chart type, axis minimum, axis maximum] */
    public const LIST = [
        'temperature' => ['Temperature', '°C', 'weather', 1, '#e4572e', 'line', null, null],
        'feels_like' => ['Feels-like temperature', '°C', 'weather', 1, '#d1495b', 'line', null, null],
        'dew_point' => ['Dew point', '°C', 'weather', 1, '#00798c', 'line', null, null],
        'humidity' => ['Relative humidity', '%', 'weather', 0, '#0077b6', 'line', 0, 100],
        'pressure' => ['Atmospheric pressure', 'hPa', 'weather', 1, '#6a4c93', 'line', null, null],
        'wind_speed' => ['Wind speed', 'm/s', 'weather', 1, '#2a9d8f', 'bar', 0, null],
        'wind_gust' => ['Wind gust', 'm/s', 'weather', 1, '#1b7f74', 'bar', 0, null],
        'wind_direction' => ['Wind direction', '°', 'weather', 0, '#3d5a80', 'line', 0, 360],
        'rain' => ['Rainfall', 'mm', 'weather', 1, '#2b6cb0', 'bar', 0, null],
        'rain_rate' => ['Rain rate', 'mm/h', 'weather', 1, '#4c8bd6', 'bar', 0, null],
        'snow_depth' => ['Snow depth', 'cm', 'weather', 0, '#8ea8c3', 'line', 0, null],
        'uv_index' => ['UV index', 'UVI', 'weather', 1, '#f59e0b', 'line', 0, null],
        'solar_radiation' => ['Solar radiation', 'W/m²', 'weather', 0, '#f4a261', 'line', 0, null],
        'illuminance' => ['Illuminance', 'lx', 'weather', 0, '#e9c46a', 'line', 0, null],
        'cloud_cover' => ['Cloud cover', '%', 'weather', 0, '#94a3b8', 'line', 0, 100],
        'visibility' => ['Visibility', 'km', 'weather', 1, '#64748b', 'line', 0, null],
        'co2' => ['CO₂ concentration', 'ppm', 'air', 0, '#8d6e63', 'line', 0, null],
        'pm1' => ['PM1 particles', 'µg/m³', 'air', 1, '#9a6b4f', 'line', 0, null],
        'pm2_5' => ['PM2.5 particles', 'µg/m³', 'air', 1, '#b5651d', 'line', 0, null],
        'pm10' => ['PM10 particles', 'µg/m³', 'air', 1, '#7f5539', 'line', 0, null],
        'voc' => ['VOC index', '', 'air', 0, '#7c6f64', 'line', 0, null],
        'co' => ['Carbon monoxide', 'ppm', 'air', 1, '#6b705c', 'line', 0, null],
        'no2' => ['Nitrogen dioxide', 'µg/m³', 'air', 1, '#a05a2c', 'line', 0, null],
        'o3' => ['Ozone', 'µg/m³', 'air', 1, '#5e8c61', 'line', 0, null],
        'so2' => ['Sulphur dioxide', 'µg/m³', 'air', 1, '#c08b30', 'line', 0, null],
        'radon' => ['Radon', 'Bq/m³', 'air', 0, '#8a5a83', 'line', 0, null],
        'aqi' => ['Air quality index', 'AQI', 'air', 0, '#588157', 'line', 0, null],
        'soil_moisture' => ['Soil moisture', '%', 'water', 0, '#7a9a01', 'line', 0, 100],
        'soil_temperature' => ['Soil temperature', '°C', 'water', 1, '#a47148', 'line', null, null],
        'leaf_wetness' => ['Leaf wetness', '%', 'water', 0, '#52b788', 'line', 0, 100],
        'water_temperature' => ['Water temperature', '°C', 'water', 1, '#0096c7', 'line', null, null],
        'water_level' => ['Water level', 'cm', 'water', 0, '#1d4e89', 'line', null, null],
        'tank_level' => ['Tank level', '%', 'water', 0, '#2a6f97', 'line', 0, 100],
        'flow_rate' => ['Flow rate', 'l/min', 'water', 1, '#168aad', 'line', 0, null],
        'water_consumption' => ['Water meter reading', 'm³', 'water', 3, '#0077b6', 'line', null, null],
        'ph' => ['pH', 'pH', 'water', 2, '#6a994e', 'line', 0, 14],
        'conductivity' => ['Conductivity', 'µS/cm', 'water', 0, '#4d908e', 'line', 0, null],
        'power' => ['Power', 'W', 'energy', 0, '#c2410c', 'line', null, null],
        'solar_power' => ['Solar power output', 'kW', 'energy', 2, '#f59e0b', 'line', 0, null],
        'grid_import' => ['Power drawn from grid', 'kW', 'energy', 2, '#b91c1c', 'line', 0, null],
        'grid_export' => ['Power fed to grid', 'kW', 'energy', 2, '#15803d', 'line', 0, null],
        'electricity_meter' => ['Electricity meter reading', 'kWh', 'energy', 2, '#b45309', 'line', null, null],
        'energy_consumed' => ['Energy consumption', 'kWh', 'energy', 2, '#dc6b19', 'bar', 0, null],
        'energy_produced' => ['Energy production', 'kWh', 'energy', 2, '#2f9e44', 'bar', 0, null],
        'voltage' => ['Voltage', 'V', 'energy', 1, '#7c3aed', 'line', null, null],
        'current' => ['Current', 'A', 'energy', 2, '#0ea5e9', 'line', null, null],
        'power_factor' => ['Power factor', '', 'energy', 2, '#64748b', 'line', 0, 1],
        'frequency' => ['Grid frequency', 'Hz', 'energy', 2, '#334155', 'line', null, null],
        'battery_level' => ['Battery charge', '%', 'energy', 0, '#16a34a', 'line', 0, 100],
        'battery_voltage' => ['Battery voltage', 'V', 'energy', 2, '#4d7c0f', 'line', null, null],
        'gas_meter' => ['Gas meter reading', 'm³', 'energy', 3, '#e76f51', 'line', null, null],
        'noise' => ['Noise level', 'dB', 'other', 1, '#6d597a', 'line', 0, null],
        'device_temperature' => ['Device temperature', '°C', 'other', 1, '#b56576', 'line', null, null],
    ];

    /** @return int number of types that were created */
    public static function sync(): int
    {
        $created = 0;
        foreach (self::LIST as $key => [$name, $unit, $category, $decimals, $color, $chart, $min, $max]) {
            $type = MeasurementType::firstOrNew(['key' => $key]);
            if ($type->exists) {
                continue;
            }
            $type->fill(['name' => $name, 'unit' => $unit, 'category' => $category, 'decimals' => $decimals, 'color' => $color, 'chart_type' => $chart, 'y_min' => $min, 'y_max' => $max]);
            $type->forceFill(['is_system' => true, 'is_public' => true])->save();
            $created++;
        }

        return $created;
    }
}
