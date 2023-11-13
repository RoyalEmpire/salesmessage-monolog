<?php
declare(strict_types=1);

namespace Monolog;

use Monolog\Formatter\SalesmessageLineFormatter;
use Monolog\Logger;
use Throwable;

/**
 * Logging Extender to provide more info and avoid giant logs
 */
class SalesmessageLogger
{
    private const LINE_FORMATTER_BACKTRACE_DEPTH = 10;

    /**
     * @return void
     */
    public function __invoke(): void
    {
        $logger = new Logger('salesmessageLogger');

        try {
            if (function_exists('app')) {
                $traceId = app()->make('request_trace_id');
                $isConsole = app()->runningInConsole();
            }
            if (function_exists('request')) {
                $clientIp = request()?->getClientIp() ?? null;
                $userId = request()?->user()?->id ?? null;
            }

        } catch (Throwable) {
            $traceId = null;
            $clientIp = null;
            $isConsole = false;
            $userId = null;
        }

        foreach ($logger->getHandlers() as $handler) {
            $handler->pushProcessor(function (array $record) use ($traceId, $clientIp, $isConsole, $userId) {
                $contextBytes = mb_strlen(json_encode($record, JSON_NUMERIC_CHECK), '8bit');

                $giantLogs = getenv('GIANT_LOGS_THRESHOLD') ?: 10000;

                if ($contextBytes > $giantLogs) {
                    $record['context']['giant_log_detected'] = true;
                }

                $record['context']['trace_id'] = $traceId;
                $record['context']['client_ip'] = $clientIp;
                $record['context']['is_console'] = $isConsole;
                $record['context']['user_id'] = $userId;

                if (($pid = getmypid()) !== false) {
                    $record['context']['pid'] = $pid;
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
