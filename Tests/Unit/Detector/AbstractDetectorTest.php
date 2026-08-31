<?php

declare(strict_types=1);

/*
 * This file is part of the "typo3_login_warning" TYPO3 CMS extension.
 *
 * (c) 2025-2026 Konrad Michalik <km@move-elevator.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MoveElevator\Typo3LoginWarning\Tests\Unit\Detector;

use KonradMichalik\Ttt\Attribute\WithTypo3ConfVars;
use MoveElevator\Typo3LoginWarning\Detector\AbstractDetector;
use PHPUnit\Framework\TestCase;

/**
 * AbstractDetectorTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-2.0-or-later
 */
final class AbstractDetectorTest extends TestCase
{
    private AbstractDetectorTestDouble $subject;

    protected function setUp(): void
    {
        $this->subject = new AbstractDetectorTestDouble();
    }

    public function testShouldDetectForUserReturnsTrueWithoutRestrictions(): void
    {
        $userArray = $this->createUserArray();

        self::assertTrue($this->subject->exposeShouldDetectForUser($userArray, []));
    }

    public function testShouldDetectForUserReturnsTrueForAdminWhenAffectedUsersIsAdmins(): void
    {
        $userArray = $this->createUserArray(true);

        self::assertTrue($this->subject->exposeShouldDetectForUser($userArray, ['affectedUsers' => 'admins']));
    }

    public function testShouldDetectForUserReturnsFalseForNonAdminWhenAffectedUsersIsAdmins(): void
    {
        $userArray = $this->createUserArray(false);

        self::assertFalse($this->subject->exposeShouldDetectForUser($userArray, ['affectedUsers' => 'admins']));
    }

    #[WithTypo3ConfVars(['SYS' => ['systemMaintainers' => [1, 2]]])]
    public function testShouldDetectForUserReturnsTrueForSystemMaintainerWhenAffectedUsersIsMaintainers(): void
    {
        $userArray = $this->createUserArray(false, 1);

        self::assertTrue($this->subject->exposeShouldDetectForUser($userArray, ['affectedUsers' => 'maintainers']));
    }

    #[WithTypo3ConfVars(['SYS' => ['systemMaintainers' => [2, 3]]])]
    public function testShouldDetectForUserReturnsFalseForNonSystemMaintainerWhenAffectedUsersIsMaintainers(): void
    {
        $userArray = $this->createUserArray(false, 1);

        self::assertFalse($this->subject->exposeShouldDetectForUser($userArray, ['affectedUsers' => 'maintainers']));
    }

    public function testShouldDetectForUserReturnsTrueWhenAffectedUsersIsAll(): void
    {
        $userArray = $this->createUserArray(false);

        self::assertTrue($this->subject->exposeShouldDetectForUser($userArray, [
            'affectedUsers' => 'all',
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function createUserArray(bool $isAdmin = false, int $uid = 1): array
    {
        return [
            'uid' => $uid,
            'admin' => $isAdmin,
        ];
    }
}

/**
 * AbstractDetectorTestDouble.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-2.0-or-later
 */
class AbstractDetectorTestDouble extends AbstractDetector
{
    /**
     * @param array<string, mixed> $userArray
     * @param array<string, mixed> $configuration
     */
    public function detect(array $userArray, array $configuration = [], ?\Psr\Http\Message\ServerRequestInterface $request = null): bool
    {
        return true;
    }

    /**
     * @param array<string, mixed> $userArray
     * @param array<string, mixed> $configuration
     */
    public function exposeShouldDetectForUser(array $userArray, array $configuration): bool
    {
        return $this->shouldDetectForUser($userArray, $configuration);
    }
}
