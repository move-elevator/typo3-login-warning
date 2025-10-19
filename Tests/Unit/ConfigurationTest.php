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
 * @license GPL-2.0-or-later
 */
final class ConfigurationTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        // Clean up global state
        unset($GLOBALS['TYPO3_CONF_VARS']['MAIL']['templateRootPaths'][500]);
        unset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][Configuration::EXT_KEY]);
    }

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

    public function testRegisterMailTemplateAddsTemplateRootPath(): void
    {
        Configuration::registerMailTemplate();

        self::assertArrayHasKey(500, $GLOBALS['TYPO3_CONF_VARS']['MAIL']['templateRootPaths']);
        self::assertSame(
            'EXT:typo3_login_warning/Resources/Private/Templates/Email',
            $GLOBALS['TYPO3_CONF_VARS']['MAIL']['templateRootPaths'][500],
        );
    }

    public function testRegisterHmacKeyUsesEncryptionKeyWhenNotSet(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] = 'test-encryption-key-12345';
        unset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][Configuration::EXT_KEY]['hmacKey']);

        Configuration::registerHmacKey();

        self::assertSame(
            'test-encryption-key-12345',
            $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][Configuration::EXT_KEY]['hmacKey'],
        );
    }

    public function testRegisterHmacKeyDoesNotOverwriteExistingKey(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][Configuration::EXT_KEY]['hmacKey'] = 'existing-hmac-key';
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] = 'test-encryption-key-12345';

        Configuration::registerHmacKey();

        self::assertSame(
            'existing-hmac-key',
            $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][Configuration::EXT_KEY]['hmacKey'],
        );
    }

    public function testRegisterHmacKeyUsesEmptyStringWhenEncryptionKeyNotSet(): void
    {
        unset($GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey']);
        unset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][Configuration::EXT_KEY]['hmacKey']);

        Configuration::registerHmacKey();

        self::assertSame(
            '',
            $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][Configuration::EXT_KEY]['hmacKey'],
        );
    }
}
