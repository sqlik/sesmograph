<?php

namespace App\Services;

use Aws\Sns\Message as SnsMessage;
use Aws\Sns\MessageValidator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class SnsSignatureValidator
{
    /**
     * Verify an SNS message's X.509 signature. The validator also checks
     * that the signing certificate URL points at an amazonaws.com host.
     */
    public function isValid(array $data): bool
    {
        try {
            $validator = new MessageValidator($this->certClient());

            return $validator->isValid(new SnsMessage($data));
        } catch (Throwable $e) {
            Log::warning('SNS signature validation error', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Certificate fetcher with a hard timeout and a cache. The AWS library
     * validates the cert URL host before calling this, and the URL changes
     * whenever AWS rotates the cert, so caching by URL is safe and spares a
     * blocking outbound fetch on every webhook delivery.
     *
     * @return callable(string): (string|false)
     */
    private function certClient(): callable
    {
        return function (string $certUrl): string|false {
            $cacheKey = 'sns_cert:'.sha1($certUrl);

            $cached = Cache::get($cacheKey);
            if (is_string($cached)) {
                return $cached;
            }

            $context = stream_context_create(['http' => ['timeout' => 5]]);
            $content = @file_get_contents($certUrl, false, $context);

            if (is_string($content)) {
                Cache::put($cacheKey, $content, now()->addDay());
            }

            return $content;
        };
    }
}
