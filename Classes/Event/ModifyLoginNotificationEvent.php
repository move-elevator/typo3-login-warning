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

namespace MoveElevator\Typo3LoginWarning\Event;

use MoveElevator\Typo3LoginWarning\Detector\DetectorInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;

/**
 * ModifyLoginNotificationEvent.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-2.0-or-later
 */
final class ModifyLoginNotificationEvent
{
    private bool $notificationPrevented = false;

    /**
     * @param array<string, mixed> $notificationConfig
     * @param array<string, mixed> $detectorConfig
     * @param array<string, mixed> $additionalData
     */
    public function __construct(
        private readonly BackendUserAuthentication $user,
        private readonly ServerRequestInterface $request,
        private readonly DetectorInterface $detector,
        private array $notificationConfig,
        private array $detectorConfig,
        private array $additionalData,
    ) {}

    public function getUser(): BackendUserAuthentication
    {
        return $this->user;
    }

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    public function getDetector(): DetectorInterface
    {
        return $this->detector;
    }

    /**
     * @return array<string, mixed>
     */
    public function getNotificationConfig(): array
    {
        return $this->notificationConfig;
    }

    /**
     * @param array<string, mixed> $notificationConfig
     */
    public function setNotificationConfig(array $notificationConfig): void
    {
        $this->notificationConfig = $notificationConfig;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDetectorConfig(): array
    {
        return $this->detectorConfig;
    }

    /**
     * @param array<string, mixed> $detectorConfig
     */
    public function setDetectorConfig(array $detectorConfig): void
    {
        $this->detectorConfig = $detectorConfig;
    }

    /**
     * Get additional data that will be passed to the notifier.
     *
     * @return array<string, mixed>
     */
    public function getAdditionalData(): array
    {
        return $this->additionalData;
    }

    /**
     * @param array<string, mixed> $additionalData
     */
    public function setAdditionalData(array $additionalData): void
    {
        $this->additionalData = $additionalData;
    }

    public function addAdditionalData(string $key, mixed $value): void
    {
        $this->additionalData[$key] = $value;
    }

    public function preventNotification(): void
    {
        $this->notificationPrevented = true;
    }

    public function allowNotification(): void
    {
        $this->notificationPrevented = false;
    }

    public function isNotificationPrevented(): bool
    {
        return $this->notificationPrevented;
    }
}
