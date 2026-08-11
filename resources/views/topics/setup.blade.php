<x-layouts.app :title="$topic->name.' - AWS setup'">
    @php
        $slug = $topic->awsSlug();
        $configSet = "{$slug}-ses";
        $snsTopic = "{$slug}-ses-events";
        $url = $topic->webhookUrl();
    @endphp

    <div class="mb-6 flex items-center justify-between gap-4">
        <div class="flex min-w-0 items-center gap-3">
            <h1 class="truncate text-xl font-semibold">
                <a href="{{ route('topics.show', $topic) }}" class="text-ink-soft hover:text-ink focus:outline-2 focus:outline-offset-2 focus:outline-focus">{{ $topic->name }}</a>
                <span class="text-ink-faint">/</span> AWS setup
            </h1>
        </div>
        <a href="{{ route('topics.show', $topic) }}" class="inline-flex shrink-0 items-center justify-center rounded-full border border-edge bg-surface px-4 py-2 text-sm font-medium text-ink hover:bg-edge/50 focus:outline-2 focus:outline-offset-2 focus:outline-focus">
            Back to topic
        </a>
    </div>

    <div class="max-w-3xl space-y-6">
        <x-ui.card>
            <h2 class="mb-1 font-medium">Webhook endpoint</h2>
            <p class="mb-3 text-sm text-ink-soft">AWS pushes SES events to this URL. Sesmograph never needs your AWS credentials.</p>
            <x-ui.copy-line :value="$url" />
        </x-ui.card>

        <section>
            <h2 class="mb-1 text-lg font-semibold">Connect AWS</h2>
            <p class="mb-4 text-sm text-ink-soft">
                Five steps in the AWS console, in the region you send from - or expand
                <span class="font-medium text-ink">Prefer CLI?</span> under each step.
                In the CLI commands, replace <code class="rounded border border-edge bg-surface px-1 py-0.5 text-xs">&lt;region&gt;</code> and
                <code class="rounded border border-edge bg-surface px-1 py-0.5 text-xs">&lt;account-id&gt;</code> with your values.
            </p>

            <ol class="space-y-4">
                <li>
                    <x-ui.card>
                        <h3 class="mb-1 font-medium">1. Create a configuration set</h3>
                        <p class="mb-3 text-sm text-ink-soft">A configuration set tracks what happens after emails are sent.</p>
                        <ol class="list-decimal space-y-1 pl-5 text-sm text-ink-soft">
                            <li>Go to <span class="font-medium text-ink">Amazon SES</span> (correct region)</li>
                            <li>Navigate to <span class="font-medium text-ink">Configuration sets</span></li>
                            <li>Click <span class="font-medium text-ink">Create set</span></li>
                            <li>Name: <x-ui.code :value="$configSet" /></li>
                            <li>Leave defaults, click <span class="font-medium text-ink">Create set</span></li>
                        </ol>
                        <x-cli-block>aws ses create-configuration-set \
  --configuration-set '{"Name":"{{ $configSet }}"}' \
  --region &lt;region&gt;</x-cli-block>
                    </x-ui.card>
                </li>
                <li>
                    <x-ui.card>
                        <h3 class="mb-1 font-medium">2. Create an SNS topic</h3>
                        <p class="mb-3 text-sm text-ink-soft">SNS forwards events to your webhook.</p>
                        <ol class="list-decimal space-y-1 pl-5 text-sm text-ink-soft">
                            <li>Go to <span class="font-medium text-ink">Amazon SNS</span></li>
                            <li>Click <span class="font-medium text-ink">Topics</span> -> <span class="font-medium text-ink">Create topic</span></li>
                            <li>Select <span class="font-medium text-ink">Standard</span> (not FIFO)</li>
                            <li>Name: <x-ui.code :value="$snsTopic" /></li>
                            <li>Click <span class="font-medium text-ink">Create topic</span></li>
                        </ol>
                        <x-cli-block>aws sns create-topic \
  --name "{{ $snsTopic }}" \
  --region &lt;region&gt;</x-cli-block>
                    </x-ui.card>
                </li>
                <li>
                    <x-ui.card>
                        <h3 class="mb-1 font-medium">3. Subscribe this webhook</h3>
                        <p class="mb-3 text-sm text-ink-soft">An HTTPS subscription pointing at this topic's endpoint.</p>
                        <ol class="list-decimal space-y-1 pl-5 text-sm text-ink-soft">
                            <li>From the SNS topic, click <span class="font-medium text-ink">Create subscription</span></li>
                            <li>Protocol: <span class="font-medium text-ink">HTTPS</span></li>
                            <li class="flex flex-wrap items-center gap-x-1">Endpoint: <x-ui.code :value="$url" /></li>
                            <li>Leave filter policy empty</li>
                            <li>Leave <span class="font-medium text-ink">"Enable raw message delivery"</span> unchecked</li>
                            <li>Click <span class="font-medium text-ink">Create subscription</span></li>
                            <li>Confirmation is automatic</li>
                        </ol>
                        <x-cli-block>aws sns subscribe \
  --topic-arn arn:aws:sns:&lt;region&gt;:&lt;account-id&gt;:{{ $snsTopic }} \
  --protocol https \
  --notification-endpoint "{{ $url }}" \
  --region &lt;region&gt;</x-cli-block>
                    </x-ui.card>
                </li>
                <li>
                    <x-ui.card>
                        <h3 class="mb-1 font-medium">4. Add an event destination</h3>
                        <p class="mb-3 text-sm text-ink-soft">Connect the configuration set to the SNS topic.</p>
                        <ol class="list-decimal space-y-1 pl-5 text-sm text-ink-soft">
                            <li>Go to <span class="font-medium text-ink">SES</span> -> <span class="font-medium text-ink">Configuration sets</span></li>
                            <li>Click <x-ui.code :value="$configSet" /></li>
                            <li><span class="font-medium text-ink">Event destinations</span> tab -> <span class="font-medium text-ink">Add destination</span></li>
                            <li>Select all event types: Sends, Deliveries, Bounces, Complaints, Opens, Clicks, Delivery delays, Rejections, Rendering failures, Subscriptions</li>
                            <li>Destination type: <span class="font-medium text-ink">Amazon SNS</span></li>
                            <li>Topic: <x-ui.code :value="$snsTopic" /></li>
                            <li>Enable <span class="font-medium text-ink">"Include original email headers"</span> - that is how subjects and recipients show up here</li>
                            <li>Click <span class="font-medium text-ink">Add destination</span></li>
                        </ol>
                        <x-cli-block>aws ses create-configuration-set-event-destination \
  --configuration-set-name "{{ $configSet }}" \
  --event-destination '{
    "Name":"{{ $snsTopic }}",
    "Enabled":true,
    "MatchingEventTypes":["send","reject","bounce","complaint","delivery","open","click","renderingFailure","deliveryDelay","subscription"],
    "SNSDestination":{"TopicARN":"arn:aws:sns:&lt;region&gt;:&lt;account-id&gt;:{{ $snsTopic }}"}
  }' \
  --region &lt;region&gt;</x-cli-block>
                    </x-ui.card>
                </li>
                <li>
                    <x-ui.card>
                        <h3 class="mb-1 font-medium">5. Send with the configuration set</h3>
                        <p class="mb-3 text-sm text-ink-soft">Specify the configuration set on outgoing mail - as an API parameter or a header:</p>
                        <x-ui.code-block>X-SES-CONFIGURATION-SET: {{ $configSet }}</x-ui.code-block>
                        <p class="mt-4 mb-2 text-sm text-ink-soft">
                            Or skip the header: set it as the identity's default, and SES applies it to every email sent from that address or domain.
                        </p>
                        <ol class="list-decimal space-y-1 pl-5 text-sm text-ink-soft">
                            <li>Go to <span class="font-medium text-ink">SES</span> -> <span class="font-medium text-ink">Identities</span></li>
                            <li>Open the identity you send from</li>
                            <li><span class="font-medium text-ink">Configuration set</span> tab -> <span class="font-medium text-ink">Edit</span></li>
                            <li>Pick <x-ui.code :value="$configSet" /> as the default configuration set, save</li>
                        </ol>
                        <x-cli-block>aws sesv2 put-email-identity-configuration-set-attributes \
  --email-identity your-identity.example.com \
  --configuration-set-name "{{ $configSet }}" \
  --region &lt;region&gt;</x-cli-block>
                    </x-ui.card>
                </li>
            </ol>
        </section>

        <x-ui.card>
            <h2 class="mb-1 font-medium">Optional: store full message content</h2>
            <p class="mb-3 text-sm text-ink-soft">
                SES events never carry the body. To see full messages here, have your app post the content
                right after sending, using the SES message id and an API token
                (<a href="{{ route('settings.api-tokens') }}" class="font-medium text-ink hover:underline">Settings -> API tokens</a>).
                Bodies are kept for {{ config('sesmograph.content_retention_days') }} days.
            </p>
            <x-ui.code-block>curl -X POST {{ url('/api/v1/messages') }}/&lt;ses-message-id&gt;/content \
  -H "Authorization: Bearer &lt;api-token&gt;" \
  -H "Content-Type: application/json" \
  -d '{"html":"&lt;!doctype html&gt;...","text":"Plain-text version"}'</x-ui.code-block>
        </x-ui.card>

        <x-ui.card>
            <h2 class="mb-1 font-medium">Verify it works</h2>
            <p class="text-sm text-ink-soft">Send a test email through the configuration set. Its events appear on this topic within seconds.</p>
            <x-cli-block>aws sns list-subscriptions-by-topic \
  --topic-arn arn:aws:sns:&lt;region&gt;:&lt;account-id&gt;:{{ $snsTopic }} \
  --region &lt;region&gt;

aws ses describe-configuration-set \
  --configuration-set-name "{{ $configSet }}" \
  --configuration-set-attribute-names eventDestinations \
  --region &lt;region&gt;</x-cli-block>
        </x-ui.card>
    </div>
</x-layouts.app>
