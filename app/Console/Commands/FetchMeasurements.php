<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Services\PullService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('meteoview:fetch {--device= : Only this device id} {--force : Ignore the fetch interval}')]
#[Description('Pull measurements from external endpoints configured on devices')]
class FetchMeasurements extends Command
{
    public function handle(PullService $pull): int
    {
        foreach ($pull->run($this->option('device'), (bool) $this->option('force')) as $r) {
            $this->line(json_encode($r));
        }

        return self::SUCCESS;
    }
}
