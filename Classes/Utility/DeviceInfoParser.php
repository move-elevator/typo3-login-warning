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

namespace MoveElevator\Typo3LoginWarning\Utility;

use Psr\Http\Message\ServerRequestInterface;

use function date;
use function preg_match;
use function sprintf;
use function str_replace;

/**
 * DeviceInfoParser.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-2.0-or-later
 */
final class DeviceInfoParser
{
    /**
     * @return array{userAgent: string, browser: string, os: string, date: string}|null
     */
    public static function parseFromRequest(?ServerRequestInterface $request): ?array
    {
        if (null === $request) {
            return null;
        }

        $userAgent = $request->getHeaderLine('User-Agent');
        if ('' === $userAgent) {
            return null;
        }

        return [
            'userAgent' => $userAgent,
            'browser' => self::parseBrowser($userAgent),
            'os' => self::parseOperatingSystem($userAgent),
            'date' => date(sprintf(
                '%s %s',
                $GLOBALS['TYPO3_CONF_VARS']['SYS']['ddmmyy'] ?? 'Y-m-d',
                $GLOBALS['TYPO3_CONF_VARS']['SYS']['hhmm'] ?? 'H:i',
            )),
        ];
    }

    public static function parseBrowser(string $userAgent): string
    {
        $browsers = [
            '/Edg\/([0-9.]+)/' => 'Edge',
            '/Chrome\/([0-9.]+)/' => 'Chrome',
            '/Firefox\/([0-9.]+)/' => 'Firefox',
            '/Safari\/([0-9.]+)/' => 'Safari',
            '/Opera\/([0-9.]+)/' => 'Opera',
        ];

        foreach ($browsers as $pattern => $name) {
            if (1 === preg_match($pattern, $userAgent, $matches)) {
                return $name.' '.$matches[1];
            }
        }

        return 'Unknown';
    }

    public static function parseOperatingSystem(string $userAgent): string
    {
        $operatingSystems = [
            '/Windows NT 10.0/' => 'Windows 10/11',
            '/Windows NT 6.3/' => 'Windows 8.1',
            '/Windows NT 6.2/' => 'Windows 8',
            '/Windows NT 6.1/' => 'Windows 7',
            '/Android ([0-9.]+)/' => 'Android',
            '/iPhone OS ([0-9_]+)/' => 'iOS',
            '/iPad.*OS ([0-9_]+)/' => 'iPadOS',
            '/Macintosh.*Mac OS X ([0-9._]+)/' => 'macOS',
            '/Linux/' => 'Linux',
        ];

        foreach ($operatingSystems as $pattern => $name) {
            if (1 === preg_match($pattern, $userAgent, $matches)) {
                // Check if a version was captured (group 1 exists)
                if (isset($matches[1])) {
                    $version = str_replace('_', '.', $matches[1]);

                    return $name.' '.$version;
                }

                return $name;
            }
        }

        return 'Unknown';
    }
}
