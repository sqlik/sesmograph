<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AlertChannel;
use App\Models\AlertLog;
use App\Models\AlertRule;
use Illuminate\View\View;

class AlertsController extends Controller
{
    public function index(): View
    {
        return view('settings.alerts.index', [
            'channels' => AlertChannel::query()->orderBy('name')->get(),
            'rules' => AlertRule::query()->with(['topic', 'channels'])->orderBy('id')->get(),
            'logs' => AlertLog::query()->with('topic')->latest('id')->limit(15)->get(),
        ]);
    }
}
