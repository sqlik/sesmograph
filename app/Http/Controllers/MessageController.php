<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ReadsQueryFilters;
use App\Models\Event;
use App\Models\Message;
use App\Models\Topic;
use App\Support\Csv;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MessageController extends Controller
{
    use ReadsQueryFilters;

    public function index(Request $request): View
    {
        $messages = $this->filtered($request)
            ->with('topic')
            ->orderByDesc('last_event_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('messages.index', [
            'messages' => $messages,
            'topics' => Topic::query()->orderBy('name')->get(['id', 'name', 'color']),
            'selectedTopicIds' => Topic::parseIds($request->query('topics')),
            'statuses' => array_values(array_unique(Message::STATUS_BY_EVENT)),
            'types' => Event::TYPES,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->filtered($request)->with('topic');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ses_message_id', 'topic', 'subject', 'from', 'recipients', 'status', 'last_event_at']);

            foreach ($query->lazyByIdDesc(500) as $message) {
                fputcsv($out, [
                    Csv::sanitize($message->ses_message_id),
                    Csv::sanitize($message->topic->name),
                    Csv::sanitize($message->subject),
                    Csv::sanitize($message->from_email),
                    Csv::sanitize(implode('; ', $message->recipients ?? [])),
                    $message->status,
                    $message->last_event_at?->toIso8601String(),
                ]);
            }

            fclose($out);
        }, 'messages.csv', ['Content-Type' => 'text/csv']);
    }

    private function filtered(Request $request): Builder
    {
        $this->stripArrayQuery($request);

        $topicIds = Topic::parseIds($request->query('topics'));
        $status = $this->queryString($request, 'status');
        $type = $this->queryString($request, 'type');
        $from = $this->queryDate($request, 'from');
        $to = $this->queryDate($request, 'to');
        $term = $this->queryString($request, 'q');

        return Message::query()
            ->when($request->filled('topic'), fn (Builder $q) => $q->where('topic_id', $request->integer('topic')))
            ->when($topicIds !== [], fn (Builder $q) => $q->whereIn('topic_id', $topicIds))
            ->when($status !== null, fn (Builder $q) => $q->where('status', $status))
            ->when($type !== null, fn (Builder $q) => $q->whereHas(
                'events', fn (Builder $e) => $e->where('type', $type),
            ))
            ->when($from !== null, fn (Builder $q) => $q->where('last_event_at', '>=', $from))
            ->when($to !== null, fn (Builder $q) => $q->where('last_event_at', '<', $to->addDay()))
            ->when($term !== null, function (Builder $q) use ($term) {
                $q->where(function (Builder $w) use ($term) {
                    $w->where('subject', 'like', "%{$term}%")
                        ->orWhere('from_email', 'like', "%{$term}%")
                        ->orWhere('recipients', 'like', "%{$term}%")
                        ->orWhere('ses_message_id', $term);
                });
            });
    }

    public function show(Request $request, Message $message): View
    {
        $content = $message->content;
        $html = $content?->html;

        // Remote assets are blocked by default so viewing your own mail
        // does not fire open-tracking pixels; ?images=1 loads them.
        if ($html !== null && ! $request->boolean('images')) {
            $html = preg_replace(
                '/\b(src|srcset)\s*=\s*(["\'])\s*(?:https?:)?\/\//i',
                'data-blocked-$1=$2//',
                $html,
            );

            // The rewrite only marks quoted attributes; this document
            // policy also stops unquoted src, CSS url() and legacy
            // background= fetches. The iframe is sandboxed without
            // scripts, so the policy cannot be removed from inside.
            $html = '<meta http-equiv="Content-Security-Policy"'
                .' content="default-src \'none\'; style-src \'unsafe-inline\'; img-src data:">'
                .$html;
        }

        return view('messages.show', [
            'message' => $message->load('topic'),
            'events' => $message->events()->orderBy('occurred_at')->orderBy('id')->get(),
            'content' => $content,
            'previewHtml' => $html,
        ]);
    }
}
