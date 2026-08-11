<?php

use App\Http\Controllers\Api\EventSearchController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\MessageContentController;
use App\Http\Controllers\Api\MessageTimelineController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\SuppressedController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Auth\TwoFactorSetupController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\Settings\ActivityLogController;
use App\Http\Controllers\Settings\AlertChannelController;
use App\Http\Controllers\Settings\AlertRuleController;
use App\Http\Controllers\Settings\AlertsController;
use App\Http\Controllers\Settings\ApiTokenController;
use App\Http\Controllers\Settings\AppearanceController;
use App\Http\Controllers\SuppressedAddressController;
use App\Http\Controllers\TopicController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::post('/webhooks/{token}', WebhookController::class)
    ->middleware('throttle:300,1')
    ->name('webhooks.ingest');

Route::middleware('api-token')->prefix('api/v1')->group(function () {
    Route::post('/messages/{sesMessageId}/content', [MessageContentController::class, 'store'])
        ->middleware('throttle:300,1')
        ->name('api.messages.content');

    Route::middleware('throttle:120,1')->group(function () {
        Route::get('/health', HealthController::class)->name('api.health');
        Route::get('/events', EventSearchController::class)->name('api.events');
        Route::get('/messages/{sesMessageId}', MessageTimelineController::class)->name('api.messages.timeline');
        Route::get('/stats', StatsController::class)->name('api.stats');
        Route::get('/suppressed', SuppressedController::class)->name('api.suppressed');
    });
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');

    Route::get('/two-factor/challenge', [TwoFactorChallengeController::class, 'create'])->name('two-factor.challenge');
    Route::post('/two-factor/challenge', [TwoFactorChallengeController::class, 'store'])->name('two-factor.challenge.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/two-factor/setup', [TwoFactorSetupController::class, 'show'])->name('two-factor.setup');
    Route::post('/two-factor/setup', [TwoFactorSetupController::class, 'confirm'])->name('two-factor.setup.confirm');
});

Route::middleware(['auth', 'two-factor'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/topics/{topic}/setup', [TopicController::class, 'setup'])->name('topics.setup');
    Route::resource('topics', TopicController::class);

    Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
    Route::get('/messages/export', [MessageController::class, 'export'])->name('messages.export');
    Route::get('/messages/{message}', [MessageController::class, 'show'])->name('messages.show');

    Route::get('/suppressed', [SuppressedAddressController::class, 'index'])->name('suppressed.index');
    Route::get('/suppressed/export', [SuppressedAddressController::class, 'export'])->name('suppressed.export');
    Route::delete('/suppressed/{address}', [SuppressedAddressController::class, 'destroy'])->name('suppressed.destroy');

    Route::get('/settings/alerts', [AlertsController::class, 'index'])->name('settings.alerts');
    Route::get('/settings/alerts/channels/create', [AlertChannelController::class, 'create'])->name('settings.alert-channels.create');
    Route::post('/settings/alerts/channels', [AlertChannelController::class, 'store'])->name('settings.alert-channels.store');
    Route::get('/settings/alerts/channels/{channel}/edit', [AlertChannelController::class, 'edit'])->name('settings.alert-channels.edit');
    Route::put('/settings/alerts/channels/{channel}', [AlertChannelController::class, 'update'])->name('settings.alert-channels.update');
    Route::delete('/settings/alerts/channels/{channel}', [AlertChannelController::class, 'destroy'])->name('settings.alert-channels.destroy');
    Route::post('/settings/alerts/channels/{channel}/test', [AlertChannelController::class, 'test'])->name('settings.alert-channels.test');

    Route::get('/settings/alerts/rules/create', [AlertRuleController::class, 'create'])->name('settings.alert-rules.create');
    Route::post('/settings/alerts/rules', [AlertRuleController::class, 'store'])->name('settings.alert-rules.store');
    Route::get('/settings/alerts/rules/{rule}/edit', [AlertRuleController::class, 'edit'])->name('settings.alert-rules.edit');
    Route::put('/settings/alerts/rules/{rule}', [AlertRuleController::class, 'update'])->name('settings.alert-rules.update');
    Route::delete('/settings/alerts/rules/{rule}', [AlertRuleController::class, 'destroy'])->name('settings.alert-rules.destroy');

    Route::get('/settings/activity', [ActivityLogController::class, 'index'])->name('settings.activity');

    Route::get('/settings/appearance', [AppearanceController::class, 'edit'])->name('settings.appearance');
    Route::put('/settings/appearance', [AppearanceController::class, 'update'])->name('settings.appearance.update');

    Route::get('/settings/api-tokens', [ApiTokenController::class, 'index'])->name('settings.api-tokens');
    Route::post('/settings/api-tokens', [ApiTokenController::class, 'store'])->name('settings.api-tokens.store');
    Route::delete('/settings/api-tokens/{token}', [ApiTokenController::class, 'destroy'])->name('settings.api-tokens.destroy');

    Route::get('/two-factor/recovery-codes', [TwoFactorSetupController::class, 'recoveryCodes'])->name('two-factor.recovery-codes');
    Route::post('/two-factor/recovery-codes', [TwoFactorSetupController::class, 'regenerate'])->name('two-factor.recovery-codes.regenerate');
    Route::get('/two-factor/recovery-codes/download', [TwoFactorSetupController::class, 'download'])->name('two-factor.recovery-codes.download');
});
