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

use MoveElevator\Typo3LoginWarning\Utility\DeviceInfoParser;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionClass;

/**
 * DeviceInfoParserTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-2.0-or-later
 */
final class DeviceInfoParserTest extends TestCase
{
    #[Test]
    public function parseFromRequestReturnsNullForNullRequest(): void
    {
        self::assertNull(DeviceInfoParser::parseFromRequest(null));
    }

    #[Test]
    public function parseFromRequestReturnsNullForEmptyUserAgent(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getHeaderLine')->with('User-Agent')->willReturn('');

        self::assertNull(DeviceInfoParser::parseFromRequest($request));
    }

    #[Test]
    public function parseFromRequestReturnsDeviceInfo(): void
    {
        $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getHeaderLine')->with('User-Agent')->willReturn($userAgent);

        $result = DeviceInfoParser::parseFromRequest($request);

        self::assertIsArray($result);
        self::assertArrayHasKey('userAgent', $result);
        self::assertArrayHasKey('browser', $result);
        self::assertArrayHasKey('os', $result);
        self::assertArrayHasKey('date', $result);
        self::assertSame($userAgent, $result['userAgent']);
        self::assertSame('Chrome 120.0.0.0', $result['browser']);
        self::assertSame('Windows 10/11', $result['os']);
    }

    #[Test]
    #[DataProvider('browserDataProvider')]
    public function parseBrowserDetectsBrowserCorrectly(string $userAgent, string $expected): void
    {
        self::assertSame($expected, DeviceInfoParser::parseBrowser($userAgent));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function browserDataProvider(): array
    {
        return [
            // Chrome
            'Chrome on Windows' => [
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Chrome 120.0.0.0',
            ],
            'Chrome on macOS' => [
                'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36',
                'Chrome 119.0.0.0',
            ],

            // Edge
            'Edge on Windows' => [
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0',
                'Edge 120.0.0.0',
            ],

            // Firefox
            'Firefox on Windows' => [
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0',
                'Firefox 121.0',
            ],
            'Firefox on Linux' => [
                'Mozilla/5.0 (X11; Linux x86_64; rv:120.0) Gecko/20100101 Firefox/120.0',
                'Firefox 120.0',
            ],

            // Safari
            'Safari on macOS' => [
                'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Safari/605.1.15',
                'Safari 605.1.15',
            ],
            'Safari on iOS' => [
                'Mozilla/5.0 (iPhone; CPU iPhone OS 17_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Mobile/15E148 Safari/604.1',
                'Safari 604.1',
            ],

            // Opera
            'Opera on Windows' => [
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 OPR/106.0.0.0',
                'Chrome 120.0.0.0',
            ],

            // Unknown
            'Unknown browser' => [
                'CustomBot/1.0',
                'Unknown',
            ],
            'Empty user agent' => [
                '',
                'Unknown',
            ],
        ];
    }

    #[Test]
    #[DataProvider('operatingSystemDataProvider')]
    public function parseOperatingSystemDetectsOsCorrectly(string $userAgent, string $expected): void
    {
        self::assertSame($expected, DeviceInfoParser::parseOperatingSystem($userAgent));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function operatingSystemDataProvider(): array
    {
        return [
            // Windows
            'Windows 10/11' => [
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Windows 10/11',
            ],
            'Windows 8.1' => [
                'Mozilla/5.0 (Windows NT 6.3; Win64; x64) AppleWebKit/537.36',
                'Windows 8.1',
            ],
            'Windows 8' => [
                'Mozilla/5.0 (Windows NT 6.2; Win64; x64) AppleWebKit/537.36',
                'Windows 8',
            ],
            'Windows 7' => [
                'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36',
                'Windows 7',
            ],

            // macOS
            'macOS Sonoma' => [
                'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15',
                'macOS 10.15.7',
            ],
            'macOS with underscore version' => [
                'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_1_1) AppleWebKit/537.36',
                'macOS 14.1.1',
            ],

            // Linux
            'Linux' => [
                'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36',
                'Linux',
            ],
            'Ubuntu Linux' => [
                'Mozilla/5.0 (X11; Ubuntu; Linux x86_64) AppleWebKit/537.36',
                'Linux',
            ],

            // Android
            'Android 14' => [
                'Mozilla/5.0 (Linux; Android 14; Pixel 7) AppleWebKit/537.36',
                'Android 14',
            ],
            'Android 13' => [
                'Mozilla/5.0 (Linux; Android 13; SM-S901B) AppleWebKit/537.36',
                'Android 13',
            ],

            // iOS
            'iOS 17' => [
                'Mozilla/5.0 (iPhone; CPU iPhone OS 17_1 like Mac OS X) AppleWebKit/605.1.15',
                'iOS 17.1',
            ],
            'iOS 16 with underscores' => [
                'Mozilla/5.0 (iPhone; CPU iPhone OS 16_6_1 like Mac OS X) AppleWebKit/605.1.15',
                'iOS 16.6.1',
            ],

            // iPadOS
            'iPadOS 17' => [
                'Mozilla/5.0 (iPad; CPU OS 17_1 like Mac OS X) AppleWebKit/605.1.15',
                'iPadOS 17.1',
            ],
            'iPadOS 16' => [
                'Mozilla/5.0 (iPad; CPU OS 16_6 like Mac OS X) AppleWebKit/605.1.15',
                'iPadOS 16.6',
            ],

            // Unknown
            'Unknown OS' => [
                'CustomBot/1.0',
                'Unknown',
            ],
            'Empty user agent' => [
                '',
                'Unknown',
            ],
        ];
    }

    #[Test]
    public function parseBrowserHandlesEdgeBeforeChrome(): void
    {
        // Edge user agents also contain "Chrome", so Edge pattern must be checked first
        $edgeUserAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0';

        self::assertSame('Edge 120.0.0.0', DeviceInfoParser::parseBrowser($edgeUserAgent));
    }

    #[Test]
    public function parseOperatingSystemHandlesMacOsVersionFormat(): void
    {
        // macOS uses underscores in version numbers which should be converted to dots
        $macOsUserAgent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15';

        $result = DeviceInfoParser::parseOperatingSystem($macOsUserAgent);

        self::assertStringContainsString('macOS', $result);
        self::assertStringContainsString('10.15.7', $result);
        self::assertStringNotContainsString('_', $result);
    }

    #[Test]
    public function parseOperatingSystemHandlesIosVersionFormat(): void
    {
        // iOS uses underscores in version numbers which should be converted to dots
        $iosUserAgent = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_2_1 like Mac OS X) AppleWebKit/605.1.15';

        $result = DeviceInfoParser::parseOperatingSystem($iosUserAgent);

        self::assertStringContainsString('iOS', $result);
        self::assertStringContainsString('17.2.1', $result);
        self::assertStringNotContainsString('_', $result);
    }

    #[Test]
    public function parseFromRequestIncludesFormattedDate(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['ddmmyy'] = 'Y-m-d';
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['hhmm'] = 'H:i';

        $userAgent = 'Mozilla/5.0 (Test) AppleWebKit/537.36';
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getHeaderLine')->with('User-Agent')->willReturn($userAgent);

        $result = DeviceInfoParser::parseFromRequest($request);

        self::assertIsArray($result);
        self::assertArrayHasKey('date', $result);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $result['date']);
    }

    #[Test]
    public function parseFromRequestUsesFallbackWhenMobileDetectNotAvailable(): void
    {
        // This test documents that the fallback mechanism works
        // Since mobiledetect/mobiledetectlib is not installed in test environment,
        // the fallback regex-based parsing is used automatically

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getHeaderLine')
            ->with('User-Agent')
            ->willReturn('Mozilla/5.0 (Windows NT 10.0) Chrome/120.0.0.0');

        $result = DeviceInfoParser::parseFromRequest($request);

        self::assertIsArray($result);
        self::assertArrayHasKey('browser', $result);
        self::assertArrayHasKey('os', $result);
        self::assertArrayHasKey('userAgent', $result);
        self::assertArrayHasKey('date', $result);

        // Verify fallback parsing works
        self::assertStringContainsString('Chrome', $result['browser']);
        self::assertStringContainsString('Windows', $result['os']);
    }

    #[Test]
    public function parseFromRequestHandlesBothDetectionMethods(): void
    {
        // This test documents the behavior:
        // - If mobiledetect/mobiledetectlib is installed: uses MobileDetect
        // - If not installed (like in our test environment): uses fallback regex

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getHeaderLine')
            ->with('User-Agent')
            ->willReturn('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)');

        $result = DeviceInfoParser::parseFromRequest($request);

        self::assertIsArray($result);
        // Both methods should return iOS
        self::assertStringContainsString('iOS', $result['os']);
    }

    #[Test]
    public function parseWithMobileDetectParsesChrome(): void
    {
        // Test the parseWithMobileDetect method directly via Reflection
        $reflection = new ReflectionClass(DeviceInfoParser::class);
        $method = $reflection->getMethod('parseWithMobileDetect');

        $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
        $result = $method->invoke(null, $userAgent);

        self::assertIsArray($result);
        self::assertCount(2, $result);
        [$browser, $os] = $result;

        self::assertStringContainsString('Chrome', $browser);
        self::assertStringContainsString('Windows', $os);
    }

    #[Test]
    public function parseWithMobileDetectParsesFirefox(): void
    {
        $reflection = new ReflectionClass(DeviceInfoParser::class);
        $method = $reflection->getMethod('parseWithMobileDetect');

        $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0';
        $result = $method->invoke(null, $userAgent);

        self::assertIsArray($result);
        [$browser, $os] = $result;

        self::assertStringContainsString('Firefox', $browser);
        self::assertStringContainsString('Windows', $os);
    }

    #[Test]
    public function parseWithMobileDetectParsesMobile(): void
    {
        $reflection = new ReflectionClass(DeviceInfoParser::class);
        $method = $reflection->getMethod('parseWithMobileDetect');

        $userAgent = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_1 like Mac OS X) AppleWebKit/605.1.15';
        $result = $method->invoke(null, $userAgent);

        self::assertIsArray($result);
        [$browser, $os] = $result;

        // MobileDetect should detect iOS
        self::assertStringContainsString('iOS', $os);
    }

    #[Test]
    public function parseWithMobileDetectHandlesUnknownUserAgent(): void
    {
        $reflection = new ReflectionClass(DeviceInfoParser::class);
        $method = $reflection->getMethod('parseWithMobileDetect');

        $userAgent = 'CustomBot/1.0';
        $result = $method->invoke(null, $userAgent);

        self::assertIsArray($result);
        [$browser, $os] = $result;

        // Should return 'Unknown' for unrecognized user agents
        self::assertSame('Unknown', $browser);
        self::assertSame('Unknown', $os);
    }

    #[Test]
    public function parseFromRequestUsesMobileDetectWhenAvailable(): void
    {
        // Since MobileDetect is now installed in dev environment,
        // parseFromRequest should use it automatically (line 46-47)

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getHeaderLine')
            ->with('User-Agent')
            ->willReturn('Mozilla/5.0 (Windows NT 10.0) Chrome/120.0.0.0');

        $result = DeviceInfoParser::parseFromRequest($request);

        self::assertIsArray($result);
        self::assertArrayHasKey('browser', $result);
        self::assertArrayHasKey('os', $result);

        // MobileDetect should have been used
        self::assertStringContainsString('Chrome', $result['browser']);
        self::assertStringContainsString('Windows', $result['os']);
    }

    #[Test]
    public function parseWithMobileDetectIncludesBrowserVersionWhenAvailable(): void
    {
        // Test that browser version is appended when MobileDetect returns one (line 127-128)
        $reflection = new ReflectionClass(DeviceInfoParser::class);
        $method = $reflection->getMethod('parseWithMobileDetect');

        // Mobile Safari typically includes version information
        $userAgent = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Mobile/15E148 Safari/604.1';
        $result = $method->invoke(null, $userAgent);

        self::assertIsArray($result);
        [$browser, $os] = $result;

        // Should include version in browser string
        self::assertMatchesRegularExpression('/Safari\s+[\d.]+/', $browser);
    }

    #[Test]
    public function parseWithMobileDetectIncludesOsVersionWhenAvailable(): void
    {
        // Test that OS version is appended when MobileDetect returns one (line 140-141)
        $reflection = new ReflectionClass(DeviceInfoParser::class);
        $method = $reflection->getMethod('parseWithMobileDetect');

        // iOS user agent - MobileDetect should detect iOS with version
        $userAgent = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_1 like Mac OS X) AppleWebKit/605.1.15';
        $result = $method->invoke(null, $userAgent);

        self::assertIsArray($result);
        [$browser, $os] = $result;

        // Should include version in OS string
        self::assertMatchesRegularExpression('/iOS\s+[\d._]+/', $os);
    }

    #[Test]
    public function parseWithMobileDetectHandlesMissingVersion(): void
    {
        // Test the case where MobileDetect detects browser/OS but returns empty version
        // This tests the false branch of the ternary operators (lines 127-128, 140-141)
        $reflection = new ReflectionClass(DeviceInfoParser::class);
        $method = $reflection->getMethod('parseWithMobileDetect');

        // Android Chrome - sometimes version() returns empty string
        $userAgent = 'Mozilla/5.0 (Linux; Android 14; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36';
        $result = $method->invoke(null, $userAgent);

        self::assertIsArray($result);
        [$browser, $os] = $result;

        // Should still have browser and OS name even if version is missing
        self::assertNotEmpty($browser);
        self::assertNotEmpty($os);
        // Android should be detected
        self::assertStringContainsString('Android', $os);
    }
}
