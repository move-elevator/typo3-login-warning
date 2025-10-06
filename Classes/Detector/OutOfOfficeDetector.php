<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "typo3_login_warning".
 *
 * Copyright (C) 2025 Konrad Michalik <km@move-elevator.de>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

namespace MoveElevator\Typo3LoginWarning\Detector;

use TYPO3\CMS\Core\Authentication\AbstractUserAuthentication;

/**
 * OutOfOfficeDetector.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0
 */
class OutOfOfficeDetector extends AbstractDetector
{
    /**
     * @param array<string, mixed> $configuration
     * @throws \Exception
     */
    public function detect(AbstractUserAuthentication $user, array $configuration = []): bool
    {
        $timezone = ($configuration['timezone'] ?? '') !== '' ? $configuration['timezone'] : 'UTC';
        $currentTime = new \DateTime('now', new \DateTimeZone($timezone));

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
        if ($workingHours === []) {
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
     * @param array<string, mixed> $workingHours
     */
    private function isWithinWorkingHours(\DateTime $time, array $workingHours): bool
    {
        $dayOfWeek = strtolower($time->format('l'));
        $currentTime = $time->format('H:i');

        if (!isset($workingHours[$dayOfWeek])) {
            return false;
        }

        $hours = $workingHours[$dayOfWeek];

        if (is_array($hours) && isset($hours[0]) && is_array($hours[0])) {
            foreach ($hours as $timeRange) {
                if (count($timeRange) === 2 && $this->isTimeInRange($currentTime, $timeRange[0], $timeRange[1])) {
                    return true;
                }
            }
            return false;
        }

        if (is_array($hours) && count($hours) === 2) {
            return $this->isTimeInRange($currentTime, $hours[0], $hours[1]);
        }

        return false;
    }

    private function isTimeInRange(string $time, string $start, string $end): bool
    {
        return $time >= $start && $time <= $end;
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function isHoliday(\DateTime $time, array $configuration): bool
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
    private function isVacationPeriod(\DateTime $time, array $configuration): bool
    {
        $date = $time->format('Y-m-d');
        $vacationPeriods = $configuration['vacationPeriods'] ?? [];
        if (!is_array($vacationPeriods)) {
            return false;
        }

        foreach ($vacationPeriods as $period) {
            if (is_array($period) && count($period) === 2) {
                if ($date >= $period[0] && $date <= $period[1]) {
                    return true;
                }
            }
        }

        return false;
    }

}
