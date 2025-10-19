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

namespace MoveElevator\Typo3LoginWarning\Tests\Unit\Registry;

use MoveElevator\Typo3LoginWarning\Detector\DetectorInterface;
use MoveElevator\Typo3LoginWarning\Registry\DetectorRegistry;
use PHPUnit\Framework\TestCase;

/**
 * DetectorRegistryTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-2.0-or-later
 */
final class DetectorRegistryTest extends TestCase
{
    public function testGetDetectorsReturnsEmptyIterableWhenNoDetectorsRegistered(): void
    {
        $registry = new DetectorRegistry([]);

        $detectors = $registry->getDetectors();

        self::assertCount(0, $detectors);
    }

    public function testGetDetectorsReturnsSingleDetector(): void
    {
        $detector = $this->createMock(DetectorInterface::class);
        $registry = new DetectorRegistry([$detector]);

        $detectors = $registry->getDetectors();

        self::assertCount(1, $detectors);
        self::assertSame($detector, iterator_to_array($detectors)[0]);
    }

    public function testGetDetectorsReturnsMultipleDetectorsInOrder(): void
    {
        $detector1 = $this->createMock(DetectorInterface::class);
        $detector2 = $this->createMock(DetectorInterface::class);
        $detector3 = $this->createMock(DetectorInterface::class);

        $registry = new DetectorRegistry([$detector1, $detector2, $detector3]);

        $detectors = iterator_to_array($registry->getDetectors());

        self::assertCount(3, $detectors);
        self::assertSame($detector1, $detectors[0]);
        self::assertSame($detector2, $detectors[1]);
        self::assertSame($detector3, $detectors[2]);
    }

    public function testGetDetectorsReturnsIteratorThatCanBeIteratedMultipleTimes(): void
    {
        $detector = $this->createMock(DetectorInterface::class);
        $registry = new DetectorRegistry([$detector]);

        $detectors1 = $registry->getDetectors();
        $detectors2 = $registry->getDetectors();

        self::assertCount(1, $detectors1);
        self::assertCount(1, $detectors2);
    }
}
