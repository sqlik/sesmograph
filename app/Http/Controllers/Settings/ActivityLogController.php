<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Concerns\ReadsQueryFilters;
use App\Http\Controllers\Controller;
use App\Models\AlertLog;
use App\Models\ApiRequestLog;
use App\Models\ApiToken;
use App\Models\Topic;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    use ReadsQueryFilters;

    public function index(Request $request): View
    {
        // The view echoes ?topic and ?token back into hidden inputs, so
        // array-shaped values must be dropped before they reach the page.
        $this->stripArrayQuery($request);

        return view('settings.activity', [
            'alerts' => AlertLog::query()
                ->with(['rule', 'topic'])
                ->when($request->filled('topic'), fn ($query) => $query->where('topic_id', $request->integer('topic')))
                ->orderByDesc('id')
                ->paginate(15, ['*'], 'alerts_page')
                ->withQueryString(),
            'requests' => ApiRequestLog::query()
                ->with('token')
                ->when($request->filled('token'), fn ($query) => $query->where('api_token_id', $request->integer('token')))
                ->orderByDesc('id')
                ->paginate(15, ['*'], 'api_page')
                ->withQueryString(),
            'topics' => Topic::query()->orderBy('name')->get(['id', 'name']),
            'tokens' => ApiToken::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
