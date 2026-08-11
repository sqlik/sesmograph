<?php

namespace App\Http\Controllers;

use App\Models\Topic;
use App\Services\SesEventProcessor;
use App\Services\SnsSignatureValidator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WebhookController extends Controller
{
    public function __construct(
        private SnsSignatureValidator $validator,
        private SesEventProcessor $processor,
    ) {}

    public function __invoke(Request $request, string $token): Response
    {
        $topic = Topic::query()->where('webhook_token', $token)->first();

        if ($topic === null) {
            abort(404);
        }

        if (! $topic->active) {
            abort(410);
        }

        $data = json_decode($request->getContent(), true);

        if (! is_array($data) || ! isset($data['Type'])) {
            abort(400);
        }

        if (! $this->validator->isValid($data)) {
            abort(403);
        }

        // TopicArn is covered by the SNS signature, so pinning the first
        // seen ARN stops forged events from other AWS accounts that only
        // know the webhook URL. Clear it on the topic edit page to re-pin.
        $arn = $data['TopicArn'] ?? null;

        if ($topic->sns_topic_arn !== null && $arn !== $topic->sns_topic_arn) {
            Log::warning('SNS message rejected: unexpected TopicArn', [
                'topic_id' => $topic->id,
                'topic_arn' => $arn,
            ]);

            abort(403);
        }

        if ($topic->sns_topic_arn === null && is_string($arn) && $arn !== '') {
            $topic->forceFill(['sns_topic_arn' => $arn])->save();
        }

        return match ($data['Type']) {
            'SubscriptionConfirmation' => $this->confirmSubscription($topic, $data),
            'Notification' => $this->handleNotification($topic, $data),
            'UnsubscribeConfirmation' => $this->acknowledgeUnsubscribe($topic),
            default => abort(400),
        };
    }

    private function confirmSubscription(Topic $topic, array $data): Response
    {
        $url = $data['SubscribeURL'] ?? null;

        // The signature already proves origin; the host check is a second
        // fence against fetching an arbitrary URL. parse_url() returns
        // false (not null) for malformed URLs, so check for a string.
        $host = is_string($url) ? parse_url($url, PHP_URL_HOST) : null;

        if (! is_string($host) || ! Str::endsWith($host, '.amazonaws.com')) {
            abort(400);
        }

        Http::timeout(10)->get($url);

        Log::info('SNS subscription confirmed', ['topic_id' => $topic->id, 'sns_topic' => $data['TopicArn'] ?? null]);

        return response()->noContent(200);
    }

    private function handleNotification(Topic $topic, array $data): Response
    {
        $event = json_decode($data['Message'] ?? '', true);

        if (is_array($event)) {
            $this->processor->process($topic, $event);
        }

        return response()->noContent(200);
    }

    private function acknowledgeUnsubscribe(Topic $topic): Response
    {
        Log::warning('SNS subscription cancelled', ['topic_id' => $topic->id]);

        return response()->noContent(200);
    }
}
