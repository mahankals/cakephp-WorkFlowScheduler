<?php
declare(strict_types=1);

namespace WorkFlowScheduler\Utility;

use DateTime;

/**
 * CronHelper
 * 
 * Simple cron expression parser to calculate next execution time
 * Supports standard cron format: minute hour day month weekday
 */
class CronHelper
{
    /**
     * Get next execution time from cron expression
     *
     * @param string $cronExpression Cron expression (e.g., "0 * * * *" for hourly)
     * @param DateTime|null $from Starting time (default: now)
     * @return DateTime|null Next execution time or null if invalid
     */
    public static function getNextRunDate(string $cronExpression, ?DateTime $from = null): ?DateTime
    {
        if ($from === null) {
            $from = new DateTime();
        }

        $parts = preg_split('/\s+/', trim($cronExpression));

        if (count($parts) !== 5) {
            return null; // Invalid cron expression
        }

        [$minute, $hour, $day, $month, $weekday] = $parts;

        // Start from next minute
        $next = clone $from;
        $next->modify('+1 minute');
        $next->setTime((int) $next->format('H'), (int) $next->format('i'), 0);

        // Try up to 366 days (1 year + leap day)
        for ($i = 0; $i < 525600; $i++) { // 365 days x 24 hours x 60 minutes
            if (self::matchesCron($next, $minute, $hour, $day, $month, $weekday)) {
                return $next;
            }
            $next->modify('+1 minute');
        }

        return null; // No match found within a year
    }

    /**
     * Check if a datetime matches the cron expression
     */
    protected static function matchesCron(DateTime $dt, string $minute, string $hour, string $day, string $month, string $weekday): bool
    {
        $currentMinute = (int) $dt->format('i');
        $currentHour = (int) $dt->format('H');
        $currentDay = (int) $dt->format('d');
        $currentMonth = (int) $dt->format('m');
        $currentWeekday = (int) $dt->format('w'); // 0 (Sunday) to 6 (Saturday)

        if (!self::matchesCronPart($minute, $currentMinute, 0, 59)) {
            return false;
        }
        if (!self::matchesCronPart($hour, $currentHour, 0, 23)) {
            return false;
        }
        if (!self::matchesCronPart($day, $currentDay, 1, 31)) {
            return false;
        }
        if (!self::matchesCronPart($month, $currentMonth, 1, 12)) {
            return false;
        }
        if (!self::matchesCronPart($weekday, $currentWeekday, 0, 6)) {
            return false;
        }

        return true;
    }

    /**
     * Check if a value matches a cron part
     */
    protected static function matchesCronPart(string $cronPart, int $value, int $min, int $max): bool
    {
        // Asterisk means any value
        if ($cronPart === '*') {
            return true;
        }

        // Asterisk-slash-N means every N
        if (preg_match('/^\*\/(\d+)$/', $cronPart, $matches)) {
            $step = (int) $matches[1];
            return $value % $step === 0;
        }

        // N-M means range
        if (preg_match('/^(\d+)-(\d+)$/', $cronPart, $matches)) {
            $rangeMin = (int) $matches[1];
            $rangeMax = (int) $matches[2];
            return $value >= $rangeMin && $value <= $rangeMax;
        }

        // N,M,O means list
        if (strpos($cronPart, ',') !== false) {
            $values = array_map('intval', explode(',', $cronPart));
            return in_array($value, $values);
        }

        // Exact value
        return (int) $cronPart === $value;
    }

    /**
     * Get human-readable description of cron expression
     */
    public static function describe(string $cronExpression): string
    {
        $parts = preg_split('/\s+/', trim($cronExpression));

        if (count($parts) !== 5) {
            return 'Invalid cron expression';
        }

        [$minute, $hour, $day, $month, $weekday] = $parts;

        // Common patterns
        if ($cronExpression === '* * * * *') {
            return 'Every minute';
        }
        if ($cronExpression === '0 * * * *') {
            return 'Every hour';
        }
        if ($cronExpression === '0 0 * * *') {
            return 'Daily at midnight';
        }
        if (preg_match('/^\*\/(\d+) \* \* \* \*$/', $cronExpression, $matches)) {
            return "Every {$matches[1]} minutes";
        }
        if (preg_match('/^0 \*\/(\d+) \* \* \*$/', $cronExpression, $matches)) {
            return "Every {$matches[1]} hours";
        }
        if (preg_match('/^(\d+) (\d+) \* \* \*$/', $cronExpression, $matches)) {
            return "Daily at {$matches[2]}:{$matches[1]}";
        }

        return "At cron expression: {$cronExpression}";
    }

    /**
     * Validate if a cron expression is valid
     *
     * @param string $cronExpression Cron expression to validate
     * @return bool True if valid, false otherwise
     */
    public static function isValid(string $cronExpression): bool
    {
        $parts = preg_split('/\s+/', trim($cronExpression));

        if (count($parts) !== 5) {
            return false;
        }

        [$minute, $hour, $day, $month, $weekday] = $parts;

        // Validate each part
        if (!self::isValidCronPart($minute, 0, 59)) {
            return false;
        }
        if (!self::isValidCronPart($hour, 0, 23)) {
            return false;
        }
        if (!self::isValidCronPart($day, 1, 31)) {
            return false;
        }
        if (!self::isValidCronPart($month, 1, 12)) {
            return false;
        }
        if (!self::isValidCronPart($weekday, 0, 6)) {
            return false;
        }

        return true;
    }

    /**
     * Validate a single cron part
     */
    protected static function isValidCronPart(string $part, int $min, int $max): bool
    {
        // Asterisk is always valid
        if ($part === '*') {
            return true;
        }

        // Check step values (e.g., */5)
        if (preg_match('/^\*\/(\d+)$/', $part, $matches)) {
            $step = (int) $matches[1];
            return $step > 0 && $step <= $max;
        }

        // Check ranges (e.g., 1-5)
        if (preg_match('/^(\d+)-(\d+)$/', $part, $matches)) {
            $rangeMin = (int) $matches[1];
            $rangeMax = (int) $matches[2];
            return $rangeMin >= $min && $rangeMax <= $max && $rangeMin < $rangeMax;
        }

        // Check lists (e.g., 1,3,5)
        if (strpos($part, ',') !== false) {
            $values = explode(',', $part);
            foreach ($values as $value) {
                $intVal = (int) trim($value);
                if ($intVal < $min || $intVal > $max) {
                    return false;
                }
            }
            return true;
        }

        // Check single value
        $intVal = (int) $part;
        return $intVal >= $min && $intVal <= $max;
    }
}
