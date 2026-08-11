<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ReadsQueryFilters;
use App\Models\SuppressedAddress;
use App\Models\Topic;
use App\Support\Csv;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SuppressedAddressController extends Controller
{
    use ReadsQueryFilters;

    public function index(Request $request): View
    {
        $addresses = $this->filtered($request)
            ->with('topic')
            ->orderByDesc('last_event_at')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('suppressed.index', [
            'addresses' => $addresses,
            'topics' => Topic::query()->orderBy('name')->get(['id', 'name', 'color']),
            'selectedTopicIds' => Topic::parseIds($request->query('topics')),
            'reasons' => SuppressedAddress::REASONS,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->filtered($request)->with('topic');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['address', 'topic', 'reason', 'hits', 'last_event_at', 'diagnostic']);

            foreach ($query->lazyByIdDesc(500) as $address) {
                fputcsv($out, [
                    Csv::sanitize($address->address),
                    Csv::sanitize($address->topic->name),
                    $address->reason,
                    $address->hits,
                    $address->last_event_at->toIso8601String(),
                    Csv::sanitize($address->last_diagnostic),
                ]);
            }

            fclose($out);
        }, 'suppressed-addresses.csv', ['Content-Type' => 'text/csv']);
    }

    public function destroy(SuppressedAddress $address): RedirectResponse
    {
        $address->delete();

        return back()->with('status', "Removed {$address->address} from the suppressed list");
    }

    private function filtered(Request $request): Builder
    {
        $this->stripArrayQuery($request);

        $topicIds = Topic::parseIds($request->query('topics'));
        $reason = $this->queryString($request, 'reason');
        $term = $this->queryString($request, 'q');

        return SuppressedAddress::query()
            ->when($request->filled('topic'), fn (Builder $q) => $q->where('topic_id', $request->integer('topic')))
            ->when($topicIds !== [], fn (Builder $q) => $q->whereIn('topic_id', $topicIds))
            ->when($reason !== null, fn (Builder $q) => $q->where('reason', $reason))
            ->when($term !== null, fn (Builder $q) => $q->where('address', 'like', "%{$term}%"));
    }
}
