<?php

namespace App\Logging;

use App\Support\Logging\RedactContextProcessor;
use Illuminate\Log\Logger;

class TapRedactor
{
    public function __invoke(Logger $logger): void
    {
        /** @var list<string> $keys */
        $keys = config('logging.redact_keys', []);

        $logger->pushProcessor(new RedactContextProcessor($keys));
    }
}
