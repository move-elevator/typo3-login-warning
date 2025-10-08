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

namespace MoveElevator\Typo3LoginWarning\Tests\Unit;

use MoveElevator\Typo3LoginWarning\Configuration;
use PHPUnit\Framework\TestCase;

/**
 * ConfigurationTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-2.0
 */
final class ConfigurationTest extends TestCase
{
    public function testExtKeyConstant(): void
    {
        self::assertSame('typo3_login_warning', Configuration::EXT_KEY);
    }

    public function testExtNameConstant(): void
    {
        self::assertSame('Typo3LoginWarning', Configuration::EXT_NAME);
    }

    public function testConstantsAreNotEmpty(): void
    {
        self::assertNotEmpty(Configuration::EXT_KEY);
        self::assertNotEmpty(Configuration::EXT_NAME);
    }
}
