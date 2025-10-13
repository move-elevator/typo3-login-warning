<?php

declare(strict_types=1);

/*
 * This file is part of the "typo3_login_warning" TYPO3 CMS extension.
 *
 * (c) 2025 Konrad Michalik <km@move-elevator.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MoveElevator\Typo3LoginWarning\Detector;

use DateTime;
use DateTimeZone;
use Exception;
use Psr\Http\Message\ServerRequestInterface;

use function count;
use function in_array;
use function is_array;

/**
 * OutOfOfficeDetector.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-2.0-or-later
 */
class OutOfOfficeDetector extends AbstractDetector
{
    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $configuration
     *
     * @throws Exception
     */
    public function detect(array $user, array $configuration = [], ?ServerRequestInterface $request = null): bool
    {
        $timezone = ($configuration['timezone'] ?? '') !== '' ? $configuration['timezone'] : 'UTC';
        $currentTime = $this->getCurrentTime($timezone);

        if ($this->isHoliday($currentTime, $configuration)) {
            $this->additionalData['violationDetails'] = [
                'type' => 'holiday',
                'date' => $currentTime->format('Y-m-d'),
                'time' => $currentTime->format('H:i:s'),
                'dayOfWeek' => $currentTime->format('l'),
            ];

            return true;
        }

        if ($this->isVacationPeriod($currentTime, $configuration)) {
            $this->additionalData['violationDetails'] = [
                'type' => 'vacation',
                'date' => $currentTime->format('Y-m-d'),
                'time' => $currentTime->format('H:i:s'),
                'dayOfWeek' => $currentTime->format('l'),
            ];

            return true;
        }

        $workingHours = $configuration['workingHours'] ?? [];
        if ([] === $workingHours) {
            return false;
        }

        if (!$this->isWithinWorkingHours($currentTime, $workingHours)) {
            $dayOfWeek = strtolower($currentTime->format('l'));
            $this->additionalData['violationDetails'] = [
                'type' => 'outside_hours',
                'date' => $currentTime->format('Y-m-d'),
                'time' => $currentTime->format('H:i:s'),
                'dayOfWeek' => $currentTime->format('l'),
                'workingHours' => $workingHours[$dayOfWeek] ?? null,
            ];

            return true;
        }

        return false;
    }

    /**
     * @throws Exception
     */
    protected function getCurrentTime(string $timezone): DateTime
    {
        return new DateTime('now', new DateTimeZone($timezone));
    }

    /**
     * @param array<string, mixed> $workingHours
     */
    protected function isWithinWorkingHours(DateTime $time, array $workingHours): bool
    {
        $dayOfWeek = strtolower($time->format('l'));
        $currentTime = $time->format('H:i');

        if (!isset($workingHours[$dayOfWeek])) {
            return false;
        }

        $hours = $workingHours[$dayOfWeek];

        return $this->checkTimeRanges($currentTime, $hours);
    }

    /**
     * @param array<string, mixed> $configuration
     */
    protected function isHoliday(DateTime $time, array $configuration): bool
    {
        $date = $time->format('Y-m-d');
        $holidays = $configuration['holidays'] ?? [];
        if (!is_array($holidays)) {
            return false;
        }

        return in_array($date, $holidays, true);
    }

    /**
     * @param array<string, mixed> $configuration
     */
    protected function isVacationPeriod(DateTime $time, array $configuration): bool
    {
        $date = $time->format('Y-m-d');
        $vacationPeriods = $configuration['vacationPeriods'] ?? [];
        if (!is_array($vacationPeriods)) {
            return false;
        }

        foreach ($vacationPeriods as $period) {
            if (is_array($period) && 2 === count($period)) {
                if ($date >= $period[0] && $date <= $period[1]) {
                    return true;
                }
            }
        }

        return false;
    }

    private function checkTimeRanges(string $currentTime, mixed $hours): bool
    {
        if (!is_array($hours)) {
            return false;
        }

        if ($this->isMultipleTimeRanges($hours)) {
            return $this->checkMultipleTimeRanges($currentTime, $hours);
        }

        if ($this->isSingleTimeRange($hours)) {
            return $this->isTimeInRange($currentTime, $hours[0], $hours[1]);
        }

        return false;
    }

    /**
     * @param array<mixed> $hours
     */
    private function isMultipleTimeRanges(array $hours): bool
    {
        return isset($hours[0]) && is_array($hours[0]);
    }

    /**
     * @param array<mixed> $hours
     */
    private function isSingleTimeRange(array $hours): bool
    {
        return 2 === count($hours);
    }

    /**
     * @param array<int, mixed> $ranges
     */
    private function checkMultipleTimeRanges(string $currentTime, array $ranges): bool
    {
        foreach ($ranges as $timeRange) {
            if (is_array($timeRange) && 2 === count($timeRange) && $this->isTimeInRange($currentTime, $timeRange[0], $timeRange[1])) {
                return true;
            }
        }

        return false;
    }

    private function isTimeInRange(string $time, string $start, string $end): bool
    {
        return $time >= $start && $time <= $end;
    }
}
