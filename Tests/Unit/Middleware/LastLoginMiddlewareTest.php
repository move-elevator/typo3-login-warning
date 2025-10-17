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

namespace MoveElevator\Typo3LoginWarning\Tests\Unit\Middleware;

use DateTime;
use MoveElevator\Typo3LoginWarning\Configuration;
use MoveElevator\Typo3LoginWarning\Context\LastLoginAspect;
use MoveElevator\Typo3LoginWarning\Middleware\LastLoginMiddleware;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};
use TYPO3\CMS\Backend\Routing\RouteResult;
use TYPO3\CMS\Beuser\Domain\Model\BackendUser;
use TYPO3\CMS\Beuser\Domain\Repository\BackendUserRepository;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * LastLoginMiddlewareTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-2.0-or-later
 */
final class LastLoginMiddlewareTest extends TestCase
{
    private Context&MockObject $context;
    private LastLoginMiddleware $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->context = $this->createMock(Context::class);
        $this->subject = new LastLoginMiddleware($this->context);
    }

    public function testImplementsMiddlewareInterface(): void
    {
        self::assertInstanceOf(MiddlewareInterface::class, $this->subject);
    }

    public function testProcessReturnsEarlyWhenRoutingAttributeIsNotRouteResult(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects(self::once())
            ->method('getAttribute')
            ->with('routing')
            ->willReturn(null);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $handler->expects(self::once())
            ->method('handle')
            ->with($request)
            ->willReturn($response);

        $this->context->expects(self::never())
            ->method('setAspect');

        $result = $this->subject->process($request, $handler);

        self::assertSame($response, $result);
    }

    public function testProcessReturnsEarlyWhenRouteNameIsNotLogin(): void
    {
        $routing = $this->createMock(RouteResult::class);
        $routing->expects(self::once())
            ->method('getRouteName')
            ->willReturn('backend');

        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects(self::once())
            ->method('getAttribute')
            ->with('routing')
            ->willReturn($routing);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $handler->expects(self::once())
            ->method('handle')
            ->with($request)
            ->willReturn($response);

        $this->context->expects(self::never())
            ->method('setAspect');

        $result = $this->subject->process($request, $handler);

        self::assertSame($response, $result);
    }

    public function testProcessReturnsEarlyWhenRequestBodyIsNotArray(): void
    {
        $routing = $this->createMock(RouteResult::class);
        $routing->expects(self::once())
            ->method('getRouteName')
            ->willReturn('login');

        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects(self::once())
            ->method('getAttribute')
            ->with('routing')
            ->willReturn($routing);
        $request->expects(self::once())
            ->method('getParsedBody')
            ->willReturn('not-an-array');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $handler->expects(self::once())
            ->method('handle')
            ->with($request)
            ->willReturn($response);

        $this->context->expects(self::never())
            ->method('setAspect');

        $result = $this->subject->process($request, $handler);

        self::assertSame($response, $result);
    }

    public function testProcessReturnsEarlyWhenUsernameIsNotInRequestBody(): void
    {
        $routing = $this->createMock(RouteResult::class);
        $routing->expects(self::once())
            ->method('getRouteName')
            ->willReturn('login');

        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects(self::once())
            ->method('getAttribute')
            ->with('routing')
            ->willReturn($routing);
        $request->expects(self::once())
            ->method('getParsedBody')
            ->willReturn(['password' => 'secret']);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $handler->expects(self::once())
            ->method('handle')
            ->with($request)
            ->willReturn($response);

        $this->context->expects(self::never())
            ->method('setAspect');

        $result = $this->subject->process($request, $handler);

        self::assertSame($response, $result);
    }

    public function testProcessReturnsEarlyWhenUserIsNotFound(): void
    {
        $routing = $this->createMock(RouteResult::class);
        $routing->expects(self::once())
            ->method('getRouteName')
            ->willReturn('login');

        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects(self::once())
            ->method('getAttribute')
            ->with('routing')
            ->willReturn($routing);
        $request->expects(self::once())
            ->method('getParsedBody')
            ->willReturn(['username' => 'nonexistent']);

        $repository = $this->createMock(BackendUserRepository::class);
        $repository->expects(self::once())
            ->method('findOneBy')
            ->with(['userName' => 'nonexistent'])
            ->willReturn(null);

        GeneralUtility::setSingletonInstance(BackendUserRepository::class, $repository);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $handler->expects(self::once())
            ->method('handle')
            ->with($request)
            ->willReturn($response);

        $this->context->expects(self::never())
            ->method('setAspect');

        $result = $this->subject->process($request, $handler);

        self::assertSame($response, $result);

        GeneralUtility::resetSingletonInstances([]);
    }

    public function testProcessReturnsEarlyWhenLastLoginIsNull(): void
    {
        $routing = $this->createMock(RouteResult::class);
        $routing->expects(self::once())
            ->method('getRouteName')
            ->willReturn('login');

        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects(self::once())
            ->method('getAttribute')
            ->with('routing')
            ->willReturn($routing);
        $request->expects(self::once())
            ->method('getParsedBody')
            ->willReturn(['username' => 'admin']);

        $backendUser = $this->createMock(BackendUser::class);
        $backendUser->expects(self::once())
            ->method('getLastLoginDateAndTime')
            ->willReturn(null);

        $repository = $this->createMock(BackendUserRepository::class);
        $repository->expects(self::once())
            ->method('findOneBy')
            ->with(['userName' => 'admin'])
            ->willReturn($backendUser);

        GeneralUtility::setSingletonInstance(BackendUserRepository::class, $repository);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $handler->expects(self::once())
            ->method('handle')
            ->with($request)
            ->willReturn($response);

        $this->context->expects(self::never())
            ->method('setAspect');

        $result = $this->subject->process($request, $handler);

        self::assertSame($response, $result);

        GeneralUtility::resetSingletonInstances([]);
    }

    public function testProcessSetsAspectWhenUserAndLastLoginAreAvailable(): void
    {
        $routing = $this->createMock(RouteResult::class);
        $routing->expects(self::once())
            ->method('getRouteName')
            ->willReturn('login');

        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects(self::once())
            ->method('getAttribute')
            ->with('routing')
            ->willReturn($routing);
        $request->expects(self::once())
            ->method('getParsedBody')
            ->willReturn(['username' => 'admin']);

        $lastLogin = new DateTime('2025-01-15 10:30:00');
        $backendUser = $this->createMock(BackendUser::class);
        $backendUser->expects(self::once())
            ->method('getLastLoginDateAndTime')
            ->willReturn($lastLogin);

        $repository = $this->createMock(BackendUserRepository::class);
        $repository->expects(self::once())
            ->method('findOneBy')
            ->with(['userName' => 'admin'])
            ->willReturn($backendUser);

        GeneralUtility::setSingletonInstance(BackendUserRepository::class, $repository);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $handler->expects(self::once())
            ->method('handle')
            ->with($request)
            ->willReturn($response);

        $this->context->expects(self::once())
            ->method('setAspect')
            ->with(
                Configuration::EXT_KEY,
                self::callback(function ($aspect) use ($lastLogin) {
                    return $aspect instanceof LastLoginAspect
                        && $aspect->get('last_login') === $lastLogin;
                }),
            );

        $result = $this->subject->process($request, $handler);

        self::assertSame($response, $result);

        GeneralUtility::resetSingletonInstances([]);
    }
}
