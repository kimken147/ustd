<?php

namespace App\Logging;

use Monolog\Formatter\LineFormatter;

class CloudwatchFormatter
{

    private $dateFormat = 'Y-m-d H:i:sO';

    public function __invoke($logger)
    {
        foreach ($logger->getHandlers() as $handler) {
            $handler->setFormatter($this->formatter());
        }
    }

    private function formatter()
    {
        return tap(new LineFormatter(null, $this->dateFormat, true, true), function ($formatter) {
            $formatter->includeStacktraces();
        });
    }
}
