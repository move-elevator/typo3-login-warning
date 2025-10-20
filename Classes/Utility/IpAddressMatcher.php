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

use function assert;
use function count;
use function explode;
use function filter_var;
use function inet_pton;
use function ord;
use function str_contains;
use function strlen;

/**
 * IpAddressMatcher.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-2.0-or-later
 */
final class IpAddressMatcher
{
    /**
     * @param array<int, string> $whitelist
     */
    public static function isWhitelisted(string $ipAddress, array $whitelist): bool
    {
        if ('' === $ipAddress || [] === $whitelist) {
            return false;
        }

        if (false === filter_var($ipAddress, \FILTER_VALIDATE_IP)) {
            return false;
        }

        foreach ($whitelist as $entry) {
            $entry = trim($entry);
            if ('' === $entry) {
                continue;
            }

            if (str_contains($entry, '/')) {
                if (self::matchesCidr($ipAddress, $entry)) {
                    return true;
                }
            } elseif ($ipAddress === $entry) {
                return true;
            }
        }

        return false;
    }

    private static function matchesCidr(string $ipAddress, string $cidr): bool
    {
        $cidrParts = self::parseCidr($cidr);
        if (null === $cidrParts) {
            return false;
        }

        [$subnet, $mask] = $cidrParts;

        $ipBinary = inet_pton($ipAddress);
        $subnetBinary = inet_pton($subnet);

        assert(false !== $ipBinary && false !== $subnetBinary);

        if (!self::validateBinaryIps($ipBinary, $subnetBinary, $mask)) {
            return false;
        }

        return self::compareBinaryIps($ipBinary, $subnetBinary, $mask);
    }

    /**
     * @return array{string, int}|null
     */
    private static function parseCidr(string $cidr): ?array
    {
        $parts = explode('/', $cidr, 2);
        if (2 !== count($parts)) {
            return null;
        }

        [$subnet, $maskString] = $parts;
        $maskString = trim($maskString);

        if ('' === $maskString) {
            return null;
        }

        $mask = (int) $maskString;

        if (0 === $mask && '0' !== $maskString) {
            return null;
        }

        if (false === filter_var($subnet, \FILTER_VALIDATE_IP)) {
            return null;
        }

        return [$subnet, $mask];
    }

    private static function validateBinaryIps(string $ipBinary, string $subnetBinary, int $mask): bool
    {
        if (strlen($ipBinary) !== strlen($subnetBinary)) {
            return false;
        }

        $maxMask = 4 === strlen($ipBinary) ? 32 : 128;

        return !($mask < 0 || $mask > $maxMask);
    }

    private static function compareBinaryIps(string $ipBinary, string $subnetBinary, int $mask): bool
    {
        $bytesToCheck = (int) ($mask / 8);
        $bitsToCheck = $mask % 8;

        for ($i = 0; $i < $bytesToCheck; ++$i) {
            if ($ipBinary[$i] !== $subnetBinary[$i]) {
                return false;
            }
        }

        if ($bitsToCheck > 0) {
            $ipByte = ord($ipBinary[$bytesToCheck]);
            $subnetByte = ord($subnetBinary[$bytesToCheck]);
            $bitMask = ~((1 << (8 - $bitsToCheck)) - 1);

            if (($ipByte & $bitMask) !== ($subnetByte & $bitMask)) {
                return false;
            }
        }

        return true;
    }
}
