# AGENTS.md

## Project overview

TYPO3 extension `typo3_login_warning` (`move-elevator/typo3-login-warning`). It extends the TYPO3 backend login warning and sends email notifications to administrators about suspicious backend logins: new IP (optional geolocation), long time without login, and logins outside working hours.

- PHP: `~8.2 || ~8.3 || ~8.4 || ~8.5`
- TYPO3: `^12.0 || ^13.0 || ^14.3` (CI tests 12.4, 13.4, 14.3)
- Namespace: `MoveElevator\Typo3LoginWarning`

## Structure

- `Classes/Detector/` detectors implementing `DetectorInterface` (`NewIpDetector`, `LongTimeNoSeeDetector`, `OutOfOfficeDetector`)
- `Classes/Notification/` notifiers implementing `NotifierInterface` (`EmailNotification`)
- `Classes/Registry/` `DetectorRegistry` and `NotificationRegistry`, filled from tagged DI services
- `Classes/Event/` PSR-14 `ModifyLoginNotificationEvent`, dispatched before a notification is sent
- `Classes/Security/` `LoginNotification` listener for `AfterUserLoggedInEvent`
- `Classes/Middleware/`, `Classes/Context/` last login tracking (`LastLoginMiddleware`, `LastLoginAspect`)
- `Classes/Domain/Repository/` IP log persistence (table `tx_typo3loginwarning_iplog`)
- `Classes/Service/`, `Classes/Utility/`, `Classes/Command/`, `Classes/Upgrades/`, `Classes/Configuration.php`
- `Configuration/` `Services.yaml`, `RequestMiddlewares.php`
- `Resources/Private/Templates/Email/` Fluid email templates, named after the detector class (`.html` and `.txt`)
- `Tests/Unit/` PHPUnit tests, mirrors `Classes/`
- `Tests/CGL/` separate Composer project with all linters, PHPStan and Rector
- `Documentation/` extension documentation

## Development commands

Development runs in DDEV. Install dependencies with `ddev start` and `ddev composer install`.

- `ddev cgl lint` runs all linters (composer, editorconfig, php)
- `ddev cgl fix` fixes all auto-fixable issues
- `ddev cgl sca` runs static analysis (PHPStan)
- `ddev cgl migration` runs Rector
- `ddev composer test` runs the tests without coverage
- `ddev install all` (or `ddev install 13`) sets up the TYPO3 test instances

Inside `Tests/CGL` the same scripts exist as Composer scripts (`lint`, `fix`, `sca`, `analyze`, `migration`). The root `composer cgl` script forwards to them.

## Testing

- PHPUnit config: `phpunit.xml`, tests in `Tests/Unit/`
- `composer test` runs without coverage, `composer test:coverage` uses `XDEBUG_MODE=coverage`
- Single test: `XDEBUG_MODE=off vendor/bin/phpunit -c phpunit.xml --no-coverage Tests/Unit/Detector/NewIpDetectorTest.php`
- Cover positive and negative cases for every detector, including user role filtering
- CI (reusable workflow `tests-typo3.yml`) runs the matrix PHP 8.2 to 8.5 against TYPO3 12.4, 13.4 and 14.3

## Code style and static analysis

- PHP CS Fixer, config in `Tests/CGL/.php-cs-fixer.php`
- PHPStan level 8 with the `konradmichalik/phpstan-typo3-preset`, config in `Tests/CGL/phpstan.neon`
- Rector, config in `Tests/CGL/rector.php`
- `composer-dependency-analyser` and `composer normalize` for Composer files
- EditorConfig is enforced (`ec --git-only`)
- The `cgl` GitHub workflow runs the checks on every push

## Git workflow

- Branch from `main`, open a pull request
- Commit format: `<type>: <description>` with type `feat`, `fix`, `refactor`, `docs`, `test`, `chore`, `perf` or `ci`
- One commit per logical change, single line message
- No co-author trailers
- Never skip hooks (`--no-verify`)
