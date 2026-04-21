<?php

namespace App\Http\Middleware;

use App\Jobs\LogApiRequestJob;
use App\Support\LogRedactor;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class LogApiRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = (string) Str::ulid();
        $request->attributes->set('request_id', $requestId);
        $request->attributes->set('_log_start_us', hrtime(true));

        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        $startNs = $request->attributes->get('_log_start_us');
        $durationMs = $startNs !== null
            ? (int) round((hrtime(true) - $startNs) / 1_000_000)
            : 0;

        $requestId = $request->attributes->get('request_id', (string) Str::ulid());

        $rawHeaders = $request->headers->all();
        $headers = LogRedactor::redactHeaders(
            $rawHeaders,
            config('logging.header_deny', [])
        );

        $redactKeys = config('logging.redact_keys', []);

        if ($request->files->count() > 0) {
            $body = LogRedactor::summarizeMultipartBody($request, $redactKeys);
        } else {
            $body = LogRedactor::redactBody($request->all(), $redactKeys);
        }

        $payload = [
            'request_id' => $requestId,
            'user_id' => $request->user('api')?->id,
            'method' => $request->method(),
            'path' => $request->path(),
            'route_name' => $request->route()?->getName(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'headers' => $headers,
            'body' => is_array($body) ? $body : [],
            'response_status' => $response->getStatusCode(),
            'response_size_bytes' => strlen((string) $response->getContent()),
            'duration_ms' => $durationMs,
        ];

        LogApiRequestJob::dispatch($payload);
    }
}
