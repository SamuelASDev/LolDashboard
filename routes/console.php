<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('riot:recover')->everyFiveMinutes()->withoutOverlapping();

Schedule::command('riot:cutoffs')->dailyAt('03:10')->timezone('America/Sao_Paulo')->withoutOverlapping(10);
