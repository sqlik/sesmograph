<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('app:prune-content')->dailyAt('03:15');
Schedule::command('app:prune-events')->dailyAt('03:30');
Schedule::command('app:evaluate-alerts')->everyFiveMinutes();
