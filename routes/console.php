<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('meteoview:fetch')->everyMinute()->withoutOverlapping();
