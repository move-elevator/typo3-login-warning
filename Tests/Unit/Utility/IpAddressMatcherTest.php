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

namespace MoveElevator\Typo3LoginWarning\Tests\Unit\Utility;

use MoveElevator\Typo3LoginWarning\Utility\IpAddressMatcher;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;

/**
 * IpAddressMatcherTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-2.0-or-later
 */
final class IpAddressMatcherTest extends TestCase
{
    #[Test]
    public function isWhitelistedReturnsFalseForEmptyIpAddress(): void
    {
        self::assertFalse(IpAddressMatcher::isWhitelisted('', ['192.168.1.1']));
    }

    #[Test]
    public function isWhitelistedReturnsFalseForEmptyWhitelist(): void
    {
        self::assertFalse(IpAddressMatcher::isWhitelisted('192.168.1.1', []));
    }

    #[Test]
    public function isWhitelistedReturnsFalseForInvalidIpAddress(): void
    {
        self::assertFalse(IpAddressMatcher::isWhitelisted('invalid-ip', ['192.168.1.1']));
        self::assertFalse(IpAddressMatcher::isWhitelisted('999.999.999.999', ['192.168.1.1']));
        self::assertFalse(IpAddressMatcher::isWhitelisted('192.168.1', ['192.168.1.1']));
    }

    #[Test]
    public function isWhitelistedReturnsTrueForExactMatch(): void
    {
        self::assertTrue(IpAddressMatcher::isWhitelisted('192.168.1.1', ['192.168.1.1']));
        self::assertTrue(IpAddressMatcher::isWhitelisted('10.0.0.1', ['192.168.1.1', '10.0.0.1']));
    }

    #[Test]
    public function isWhitelistedReturnsFalseForNoMatch(): void
    {
        self::assertFalse(IpAddressMatcher::isWhitelisted('192.168.1.100', ['192.168.1.1']));
        self::assertFalse(IpAddressMatcher::isWhitelisted('10.0.0.1', ['192.168.1.1', '192.168.1.2']));
    }

    #[Test]
    public function isWhitelistedHandlesWhitespaceInWhitelist(): void
    {
        self::assertTrue(IpAddressMatcher::isWhitelisted('192.168.1.1', [' 192.168.1.1 ', '10.0.0.1']));
        self::assertFalse(IpAddressMatcher::isWhitelisted('192.168.1.1', ['', '  ', '10.0.0.1']));
    }

    #[Test]
    #[DataProvider('cidrMatchDataProvider')]
    public function isWhitelistedMatchesCidrNotation(string $ipAddress, string $cidr, bool $expected): void
    {
        self::assertSame($expected, IpAddressMatcher::isWhitelisted($ipAddress, [$cidr]));
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function cidrMatchDataProvider(): array
    {
        return [
            // IPv4 /24 subnet
            '192.168.1.0/24 matches 192.168.1.1' => ['192.168.1.1', '192.168.1.0/24', true],
            '192.168.1.0/24 matches 192.168.1.255' => ['192.168.1.255', '192.168.1.0/24', true],
            '192.168.1.0/24 matches 192.168.1.0' => ['192.168.1.0', '192.168.1.0/24', true],
            '192.168.1.0/24 does not match 192.168.2.1' => ['192.168.2.1', '192.168.1.0/24', false],
            '192.168.1.0/24 does not match 192.168.0.255' => ['192.168.0.255', '192.168.1.0/24', false],

            // IPv4 /16 subnet
            '192.168.0.0/16 matches 192.168.1.1' => ['192.168.1.1', '192.168.0.0/16', true],
            '192.168.0.0/16 matches 192.168.255.255' => ['192.168.255.255', '192.168.0.0/16', true],
            '192.168.0.0/16 does not match 192.169.1.1' => ['192.169.1.1', '192.168.0.0/16', false],

            // IPv4 /32 (single IP)
            '192.168.1.1/32 matches 192.168.1.1' => ['192.168.1.1', '192.168.1.1/32', true],
            '192.168.1.1/32 does not match 192.168.1.2' => ['192.168.1.2', '192.168.1.1/32', false],

            // IPv4 /8 subnet
            '10.0.0.0/8 matches 10.1.2.3' => ['10.1.2.3', '10.0.0.0/8', true],
            '10.0.0.0/8 matches 10.255.255.255' => ['10.255.255.255', '10.0.0.0/8', true],
            '10.0.0.0/8 does not match 11.0.0.1' => ['11.0.0.1', '10.0.0.0/8', false],

            // IPv4 /30 subnet (4 IPs)
            '192.168.1.0/30 matches 192.168.1.0' => ['192.168.1.0', '192.168.1.0/30', true],
            '192.168.1.0/30 matches 192.168.1.3' => ['192.168.1.3', '192.168.1.0/30', true],
            '192.168.1.0/30 does not match 192.168.1.4' => ['192.168.1.4', '192.168.1.0/30', false],

            // IPv6 /64 subnet
            '2001:db8::/64 matches 2001:db8::1' => ['2001:db8::1', '2001:db8::/64', true],
            '2001:db8::/64 matches 2001:db8::ffff:ffff:ffff:ffff' => ['2001:db8::ffff:ffff:ffff:ffff', '2001:db8::/64', true],
            '2001:db8::/64 does not match 2001:db8:1::1' => ['2001:db8:1::1', '2001:db8::/64', false],

            // IPv6 /128 (single IP)
            '2001:db8::1/128 matches 2001:db8::1' => ['2001:db8::1', '2001:db8::1/128', true],
            '2001:db8::1/128 does not match 2001:db8::2' => ['2001:db8::2', '2001:db8::1/128', false],

            // IPv6 /48 subnet
            '2001:db8::/48 matches 2001:db8:0:1::1' => ['2001:db8:0:1::1', '2001:db8::/48', true],
            '2001:db8::/48 does not match 2001:db8:1::1' => ['2001:db8:1::1', '2001:db8::/48', false],
        ];
    }

    #[Test]
    public function isWhitelistedReturnsFalseForInvalidCidr(): void
    {
        self::assertFalse(IpAddressMatcher::isWhitelisted('192.168.1.1', ['192.168.1.0/']));
        self::assertFalse(IpAddressMatcher::isWhitelisted('192.168.1.1', ['192.168.1.0/33']));
        self::assertFalse(IpAddressMatcher::isWhitelisted('192.168.1.1', ['192.168.1.0/-1']));
        self::assertFalse(IpAddressMatcher::isWhitelisted('192.168.1.1', ['invalid/24']));
    }

    #[Test]
    public function isWhitelistedReturnsFalseForMixedIpVersions(): void
    {
        // IPv4 address with IPv6 CIDR
        self::assertFalse(IpAddressMatcher::isWhitelisted('192.168.1.1', ['2001:db8::/64']));

        // IPv6 address with IPv4 CIDR
        self::assertFalse(IpAddressMatcher::isWhitelisted('2001:db8::1', ['192.168.1.0/24']));
    }

    #[Test]
    public function isWhitelistedSupportsMultipleEntries(): void
    {
        $whitelist = [
            '127.0.0.1',
            '192.168.1.0/24',
            '10.0.0.0/8',
            '2001:db8::/64',
        ];

        // Exact match
        self::assertTrue(IpAddressMatcher::isWhitelisted('127.0.0.1', $whitelist));

        // CIDR matches
        self::assertTrue(IpAddressMatcher::isWhitelisted('192.168.1.50', $whitelist));
        self::assertTrue(IpAddressMatcher::isWhitelisted('10.123.45.67', $whitelist));
        self::assertTrue(IpAddressMatcher::isWhitelisted('2001:db8::cafe', $whitelist));

        // No match
        self::assertFalse(IpAddressMatcher::isWhitelisted('8.8.8.8', $whitelist));
        self::assertFalse(IpAddressMatcher::isWhitelisted('192.168.2.1', $whitelist));
    }

    #[Test]
    public function isWhitelistedHandlesLocalhostIpv6(): void
    {
        self::assertTrue(IpAddressMatcher::isWhitelisted('::1', ['::1']));
        self::assertTrue(IpAddressMatcher::isWhitelisted('::1', ['::1/128']));
    }

    #[Test]
    public function isWhitelistedHandlesPrivateNetworks(): void
    {
        // Private IPv4 ranges
        self::assertTrue(IpAddressMatcher::isWhitelisted('192.168.100.100', ['192.168.0.0/16']));
        self::assertTrue(IpAddressMatcher::isWhitelisted('172.16.50.50', ['172.16.0.0/12']));
        self::assertTrue(IpAddressMatcher::isWhitelisted('10.20.30.40', ['10.0.0.0/8']));
    }

    #[Test]
    public function isWhitelistedHandlesEdgeCases(): void
    {
        // /0 should match everything
        self::assertTrue(IpAddressMatcher::isWhitelisted('1.2.3.4', ['0.0.0.0/0']));
        self::assertTrue(IpAddressMatcher::isWhitelisted('192.168.1.1', ['0.0.0.0/0']));

        // IPv6 /0
        self::assertTrue(IpAddressMatcher::isWhitelisted('2001:db8::1', ['::/0']));
    }
}
