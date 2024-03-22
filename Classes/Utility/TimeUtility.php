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
        $indexerRunningTimeHMS = TimeUtility::getTimeHoursMinutesSeconds($indexerRunningTime);

        $result = '';
        if ($indexerRunningTimeHMS['h']) {
            $result .= ' ' . $indexerRunningTimeHMS['h'] . ' h';
        }
        if ($indexerRunningTimeHMS['m']) {
            $result .= ' ' . $indexerRunningTimeHMS['m'] . ' m';
        }
        if ($indexerRunningTimeHMS['s']) {
            $result .= ' ' . $indexerRunningTimeHMS['s'] . ' s';
        }

        return $result;
    }


}
