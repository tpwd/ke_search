<?php

namespace Tpwd\KeSearch\Utility;

class TimeUtility
{
    public static function getRunningTime(int $indexerStartTime): int
    {
        return $indexerStartTime ? (time() - $indexerStartTime) : -1;
    }

    public static function getTimeHoursMinutesSeconds(int $time): array
    {
        return ($time > 0) ?
            [
                'h' => floor($time / 3600),
                'm' => (int)($time / 60) % 60,
                's' => $time % 60,
            ]
            : [];
    }

    public static function getRunningTimeHumanReadable(int $indexerStartTime): string
    {
        $indexerRunningTime = TimeUtility::getRunningTime($indexerStartTime);
        if ($indexerRunningTime < 0) {
            return '';
        }
        return self::getSecondsHumanReadable($indexerRunningTime);
    }

    public static function getSecondsHumanReadable(int $seconds): string
    {
        $timeHMS = TimeUtility::getTimeHoursMinutesSeconds($seconds);
        $result = '';
        if ($timeHMS['h'] ?? null) {
            $result .= $timeHMS['h'] . ' h';
        }
        if (strlen($result) > 0) {
            $result .= ' ';
        }
        if ($timeHMS['m'] ?? null) {
            $result .= $timeHMS['m'] . ' m';
        }
        if (strlen($result) > 0) {
            $result .= ' ';
        }
        if ($timeHMS['s'] ?? null) {
            $result .= $timeHMS['s'] . ' s';
        }
        return $result;
    }
}
