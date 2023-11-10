<?php
declare(strict_types=1);

namespace Monolog;

use Monolog\Formatter\SalesmessageLineFormatter;
use Monolog\Logger;

/**
 * Logging Extender to provide more info and avoid giant logs
 */
class SalesmessageLogger
{
    private const LINE_FORMATTER_BACKTRACE_DEPTH = 10;

    /**
     * @param Logger $logger
     */
    public function __invoke(Logger $logger): void
    {
        var_dump('##################4#####################');
        foreach ($logger->getHandlers() as $handler) {
            $handler->pushProcessor(function (array $record) {
                $contextBytes = mb_strlen(json_encode($record, JSON_NUMERIC_CHECK), '8bit');
                if ($contextBytes > env('GIANT_LOGS_THRESHOLD', 100000)) {
                    $record['context']['giant_log_detected'] = true;
                }
                return $record;
            });

            $handler->setFormatter(
                (new SalesmessageLineFormatter(includeStacktraces: true))
                    ->setBacktraceDepth(self::LINE_FORMATTER_BACKTRACE_DEPTH)
            );
        }
    }
}
