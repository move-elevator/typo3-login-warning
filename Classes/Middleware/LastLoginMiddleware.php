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

namespace MoveElevator\Typo3LoginWarning\Middleware;

use MoveElevator\Typo3LoginWarning\Configuration;
use MoveElevator\Typo3LoginWarning\Context\LastLoginAspect;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};
use TYPO3\CMS\Backend\Routing\RouteResult;
use TYPO3\CMS\Beuser\Domain\Model\BackendUser;
use TYPO3\CMS\Beuser\Domain\Repository\BackendUserRepository;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use function array_key_exists;
use function is_array;

/**
 * LastLoginMiddleware.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-2.0-or-later
 */
class LastLoginMiddleware implements MiddlewareInterface
{
    public function __construct(
        protected readonly Context $context,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $routing = $request->getAttribute('routing');
        if (!$routing instanceof RouteResult || 'login' !== $routing->getRouteName()) {
            return $handler->handle($request);
        }

        $requestBody = $request->getParsedBody();
        if (!is_array($requestBody) || !array_key_exists('username', $requestBody)) {
            return $handler->handle($request);
        }

        $username = $requestBody['username'];
        $repository = GeneralUtility::makeInstance(BackendUserRepository::class);

        $user = $repository->findOneBy(['userName' => $username]);
        if (!$user instanceof BackendUser) {
            return $handler->handle($request);
        }

        $lastLogin = $user->getLastLoginDateAndTime();
        if (null === $lastLogin) {
            return $handler->handle($request);
        }

        $this->context->setAspect(
            Configuration::EXT_KEY,
            new LastLoginAspect($lastLogin),
        );

        return $handler->handle($request);
    }
}
