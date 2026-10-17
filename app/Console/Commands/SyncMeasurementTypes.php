<?php

namespace App\Console\Commands;

use App\Support\SystemTypes;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('meteoview:sync-types')]
#[Description('Add the built-in measurement types that are missing (existing and edited ones are kept)')]
class SyncMeasurementTypes extends Command
{
    public function handle(): int
    {
        $this->info(SystemTypes::sync().' measurement type(s) added.');

        return self::SUCCESS;
    }
}
