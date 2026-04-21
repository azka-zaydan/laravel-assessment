<?php

namespace App\Support\Logging;

use App\Support\LogRedactor;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Recursively redacts sensitive keys from the Monolog record context, using the
 * same deny-list that `LogApiRequest` uses for HTTP bodies. Without this,
 * `Log::info('...', ['password' => $x])` would leak — the middleware redactor
 * only runs on the request body, not on application Log::* calls.
 */
class RedactContextProcessor implements ProcessorInterface
{
    /** @var list<string> */
    private array $keys;

    /**
     * @param  list<string>  $keys
     */
    public function __construct(array $keys)
    {
        $this->keys = $keys;
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        if ($record->context === []) {
            return $record;
        }

        /** @var array<string,mixed> $redacted */
        $redacted = LogRedactor::redactBody($record->context, $this->keys);

        return $record->with(context: $redacted);
    }
}
