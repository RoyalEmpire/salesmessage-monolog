<?php
declare(strict_types=1);

namespace Monolog\Formatter;

use Monolog\Formatter\LineFormatter;

class SalesmessageLineFormatter extends LineFormatter
{
    public const SIMPLE_DATE = 'Y-m-d H:i:s';

    public const ERROR_MAX_NORMALIZE_DEPTH = 2;

    protected const LOG_LEVELS = [
        'ERROR'
    ];

    /**
     * Context fields to inline with message
     */
    private const CONTEXT_TO_MESSAGE_FIELDS = [
        'trace_id',
        'client_ip',
        'user_id',
        'is_console',
        'giant_log_detected',
    ];

    /**
     * Backtrace depth corresponding to the logger methods nesting level
     *
     * @var int|null
     */
    private ?int $backtraceDepth = null;

    /**
     * @inheritDoc
     */
    public function format(array $record): string
    {
        if (in_array($record['level_name'], self::LOG_LEVELS)) {
            $this->setMaxNormalizeDepth(self::ERROR_MAX_NORMALIZE_DEPTH);
        }

        // add backtrace info
        $record['message'] = $this->backtraceAwareMessage($record['message']);

        // inline context fields with the message
        $record['message'] = self::contextAwareMessage(
            $record['message'],
            $record['context'],
            self::CONTEXT_TO_MESSAGE_FIELDS
        );

        // remove inlined fields from context
        $record['context'] = self::filterFields(
            $record['context'],
            self::CONTEXT_TO_MESSAGE_FIELDS
        );

        return parent::format($record);
    }

    /**
     * Sets the backtrace depth
     *
     * @param int $backtraceDepth
     *
     * @return $this
     */
    public function setBacktraceDepth(int $backtraceDepth): static
    {
        $this->backtraceDepth = $backtraceDepth;

        return $this;
    }

    /**
     * Adds context fields to the message
     *
     * @param string $message
     * @param array  $context
     * @param array  $fieldsToAdd
     *
     * @return string
     */
    private static function contextAwareMessage(string $message, array $context, array $fieldsToAdd): string
    {
        $strings = [];
        foreach ($fieldsToAdd as $field) {
            $value = $context[$field] ?? null;

            // skip empty values to reduce the log size
            if (!empty($value)) {
                $strings[] = "$field=$value";
            }
        }

        if (!$strings) {
            return $message;
        }

        return "$message (" . implode(' ', $strings) . ")";
    }

    /**
     * Adds backtrace info to the message
     *
     * @param string $message
     *
     * @return string
     */
    private function backtraceAwareMessage(string $message): string
    {
        if (!$this->backtraceDepth) {
            return $message;
        }

        $backtrace = self::backtrace($this->backtraceDepth);
        $grandParent = array_pop($backtrace);
        $parent = array_pop($backtrace);

        if (!$parent || !$grandParent) {
            return $message;
        }

        $class = $grandParent['class'] ?? 'Unknown';
        $class = str_replace('\\', '.', $class);

        $function = $grandParent['function'] ?? 'unknown';
        $line = $parent['line'] ?? -1;

        return "$class::$function(L$line): $message";
    }

    /**
     * Adds backtrace info to the context
     *
     * @param array $context
     *
     * @return array
     */
    private function backtraceAwareContext(array $context): array
    {
        if (!$this->backtraceDepth) {
            return $context;
        }

        $backtrace = self::backtrace($this->backtraceDepth);
        array_pop($backtrace); // skip grandparent

        if (!$parent = array_pop($backtrace)) {
            return $context;
        }

        $context['_source'] = [
            'file' => $parent['file'] ?? 'unknown',
            'line' => $parent['line'] ?? -1,
        ];

        return $context;
    }

    /**
     * Gets the backtrace of specific depth
     *
     * @param int $depth
     *
     * @return array
     */
    private static function backtrace(int $depth): array
    {
        return debug_backtrace(options: DEBUG_BACKTRACE_IGNORE_ARGS, limit: $depth);
    }

    /**
     * Removes fields from an array
     *
     * @param array $arr
     * @param array $fieldsToFilter
     *
     * @return array
     */
    private static function filterFields(array $arr, array $fieldsToFilter): array
    {
        return array_diff_key($arr, array_flip($fieldsToFilter));
    }

    /**
     * Include stacktraces
     *
     * @param bool $include
     * @param callable|null $parser
     * @return $this
     */
    public function includeStacktraces(bool $include = true, ?callable $parser = null): self
    {
        $this->includeStacktraces = $include;
        if ($this->includeStacktraces) {
            $this->stacktracesParser = $parser;
        }

        return $this;
    }
}
