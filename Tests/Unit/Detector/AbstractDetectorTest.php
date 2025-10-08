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

namespace MoveElevator\Typo3LoginWarning\Tests\Unit\Detector;

use MoveElevator\Typo3LoginWarning\Detector\AbstractDetector;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Authentication\AbstractUserAuthentication;

/**
 * AbstractDetectorTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-2.0
 */
final class AbstractDetectorTest extends TestCase
{
    private AbstractDetector $subject;

    protected function setUp(): void
    {
        $this->subject = new class extends AbstractDetector {
            /**
             * @param array<string, mixed> $configuration
             */
            public function detect(AbstractUserAuthentication $user, array $configuration = []): bool
            {
                return true;
            }

            /**
             * @param array<string, mixed> $configuration
             */
            public function exposeShouldDetectForUser(AbstractUserAuthentication $user, array $configuration): bool
            {
                return $this->shouldDetectForUser($user, $configuration);
            }
        };
    }

    public function testShouldDetectForUserReturnsTrueWithoutRestrictions(): void
    {
        $user = $this->createMockUser();

        self::assertTrue($this->subject->exposeShouldDetectForUser($user, []));
    }

    public function testShouldDetectForUserReturnsTrueForAdminWhenAffectedUsersIsAdmins(): void
    {
        $user = $this->createMockUser(isAdmin: true);

        self::assertTrue($this->subject->exposeShouldDetectForUser($user, ['affectedUsers' => 'admins']));
    }

    public function testShouldDetectForUserReturnsFalseForNonAdminWhenAffectedUsersIsAdmins(): void
    {
        $user = $this->createMockUser(isAdmin: false);

        self::assertFalse($this->subject->exposeShouldDetectForUser($user, ['affectedUsers' => 'admins']));
    }

    public function testShouldDetectForUserReturnsTrueForSystemMaintainerWhenAffectedUsersIsMaintainers(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['systemMaintainers'] = [1, 2];
        $user = $this->createMockUser(uid: 1);

        self::assertTrue($this->subject->exposeShouldDetectForUser($user, ['affectedUsers' => 'maintainers']));
    }

    public function testShouldDetectForUserReturnsFalseForNonSystemMaintainerWhenAffectedUsersIsMaintainers(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['systemMaintainers'] = [2, 3];
        $user = $this->createMockUser(uid: 1);

        self::assertFalse($this->subject->exposeShouldDetectForUser($user, ['affectedUsers' => 'maintainers']));
    }

    public function testShouldDetectForUserReturnsTrueWhenAffectedUsersIsAll(): void
    {
        $user = $this->createMockUser(isAdmin: false);

        self::assertTrue($this->subject->exposeShouldDetectForUser($user, [
            'affectedUsers' => 'all',
        ]));
    }

    private function createMockUser(bool $isAdmin = false, int $uid = 1): AbstractUserAuthentication
    {
        $user = $this->createMock(AbstractUserAuthentication::class);
        $user->user = [
            'uid' => $uid,
            'admin' => $isAdmin,
        ];

        return $user;
    }
}
