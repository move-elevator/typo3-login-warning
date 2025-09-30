<div align="center">

![Extension icon](Resources/Public/Icons/Extension.svg)

# TYPO3 extension `typo3_login_warning`

[![Latest Stable Version](https://typo3-badges.dev/badge/typo3_login_warning/version/shields.svg)](https://extensions.typo3.org/extension/typo3_login_warning)
[![Supported TYPO3 versions](https://typo3-badges.dev/badge/typo3_login_warning/typo3/shields.svg)](https://extensions.typo3.org/extension/typo3_login_warning)
[![Coverage](https://img.shields.io/coverallsCoverage/github/move-elevator/typo3-login-warning?logo=coveralls)](https://coveralls.io/github/move-elevator/typo3-login-warning)
[![CGL](https://img.shields.io/github/actions/workflow/status/move-elevator/typo3-login-warning/cgl.yml?label=cgl&logo=github)](https://github.com/move-elevator/typo3-login-warning/actions/workflows/cgl.yml)
[![Tests](https://img.shields.io/github/actions/workflow/status/move-elevator/typo3-login-warning/tests.yml?label=tests&logo=github)](https://github.com/move-elevator/typo3-login-warning/actions/workflows/tests.yml)
[![License](https://poser.pugx.org/xima/typo3-login-warning/license)](LICENSE.md)

</div>

This extension extends the TYPO3 backend login [warning_mode](https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/Security/GuidelinesIntegrators/GlobalTypo3Options.html#security-global-typo3-options-warning-mode) functionality with several improvements:

- [**New IP**](#newipdetector) based warning to detect logins from new IP addresses
- [**Long Time No See**](#longtimenoseedetector) notification for infrequent users
- [**Out Of Office**](#outofofficedetector) login detection outside defined working hours, holidays, or vacation periods

> [!WARNING]
> This package is in early development and may change significantly.

> [!NOTE]
> Since I was annoyed by the constant login emails from the TYPO3 backend, but the issue of login security is still extremely important, I expanded the standard login notification functions of TYPO3 with some more or less well-known additional features.

## 🔥 Installation

### Requirements

* TYPO3 >= 13.4
* PHP 8.2+

### Composer

[![Packagist](https://img.shields.io/packagist/v/move-elevator/typo3-login-warning?label=version&logo=packagist)](https://packagist.org/packages/move-elevator/typo3-login-warning)
[![Packagist Downloads](https://img.shields.io/packagist/dt/move-elevator/typo3-login-warning?color=brightgreen)](https://packagist.org/packages/move-elevator/typo3-login-warning)

``` bash
composer require move-elevator/typo3-login-warning
```

### TER

[![TER version](https://typo3-badges.dev/badge/typo3_login_warning/version/shields.svg)](https://extensions.typo3.org/extension/typo3_login_warning)
[![TER downloads](https://typo3-badges.dev/badge/typo3_login_warning/downloads/shields.svg)](https://extensions.typo3.org/extension/typo3_login_warning)

Download the zip file from [TYPO3 extension repository (TER)](https://extensions.typo3.org/extension/typo3_login_warning).

## 🧰 Configuration

Add the desired warning detector(s) in your `ext_localconf.php`:

```php
use MoveElevator\Typo3LoginWarning\Configuration\LoginWarning;
use MoveElevator\Typo3LoginWarning\Notification\EmailNotification;

// Simple configuration with default settings using shorthand syntax
LoginWarning::newIp();
LoginWarning::longTimeNoSee();
LoginWarning::outOfOffice();

// Extended example configuration with shorthand syntax
LoginWarning::newIp([
    'hashIpAddress' => false,
    'fetchGeolocation' => false,
    'whitelist' => [
        '192.168.97.5',
    ],
    'notification' => [
        EmailNotification::class => [
            'recipient' => 'security@example.com',
        ],
    ],
]);

LoginWarning::longTimeNoSee([
    'thresholdDays' => 180,
    'notification' => [
        EmailNotification::class => [
            'recipient' => 'longterm@example.com',
        ],
    ],
]);
```

> [!IMPORTANT]
> The first detector that matches will trigger a notification and no further detectors will be checked. So the order of the detectors is important.

## 💡 Concepts

### Detectors

Detectors are used to detect certain login events. If a detector matches, a notification will be sent.

> [!TIP]
> You can implement your own detector by implementing the `MoveElevator\Typo3LoginWarning\Detector\DetectorInterface`.

The following detectors are available:

#### [NewIpDetector](Classes/Detector/NewIpDetector.php)

Detects logins from new IP addresses and triggers a warning email. 

The IP address will be stored and can be hashed for privacy reasons. You can also define a whitelist of IP addresses that will not trigger a warning. An ip geolocation lookup can be enabled to add more information to the notification email.

```php
// Extended example configuration using shorthand syntax
LoginWarning::newIp([
    'hashIpAddress' => true, // Hash the IP address for privacy (SHA-256)
    'fetchGeolocation' => true, // Enable IP geolocation lookup
    'whitelist' => [ // Define a whitelist of IP addresses that will not trigger a warning
        '127.0.0.1',
        '192.168.1.0/24',
    ],
    'notification' => [ // Override default notification configuration
        EmailNotification::class => [
            'recipient' => 'security@example.com',
        ],
    ],
]);
```

#### [LongTimeNoSeeDetector](Classes/Detector/LongTimeNoSeeDetector.php)

Detects logins after a long period of inactivity (default: 365 days).

```php
// Extended example configuration using shorthand syntax
LoginWarning::longTimeNoSee([
    'thresholdDays' => 180, // Set threshold for inactivity in days, default is 365
    'notification' => [ // Override default notification configuration
        EmailNotification::class => [
            'recipient' => 'security@example.com',
        ],
    ],
]);
```

#### [OutOfOfficeDetector](Classes/Detector/OutOfOfficeDetector.php)

Detects logins outside defined working hours, holidays, or vacation periods. Supports flexible working hours with multiple time ranges per day (e.g., lunch breaks), timezone handling, and comprehensive out-of-office period configuration.

```php
// Extended example configuration using shorthand syntax
LoginWarning::outOfOffice([
    'workingHours' => [
        'monday' => [['09:00', '12:00'], ['13:00', '17:00']], // Flexible working hours with breaks
        'tuesday' => ['09:00', '17:00'],
        'wednesday' => ['09:00', '17:00'],
        'thursday' => ['09:00', '17:00'],
        'friday' => ['09:00', '15:00'],
    ],
    'timezone' => 'Europe/Berlin', // Timezone for working hours
    'holidays' => [ // List of holidays (Y-m-d format)
        '2025-01-01',
        '2025-12-25',
    ],
    'vacationPeriods' => [ // List of vacation periods (Y-m-d format)
        ['2025-07-15', '2025-07-30'],
    ],
    'notification' => [ // Override default notification configuration
        EmailNotification::class => [
            'recipient' => 'security@example.com',
        ],
    ],
]);
```

### Notification

Notification methods are used to notify about detected login events.

> [!TIP]
> You can implement more notification methods by implementing the `MoveElevator\Typo3LoginWarning\Notification\NotificationInterface`.

The following notification methods are available:

#### [EmailNotification](Classes/Notification/EmailNotification.php)

Sends a warning email to a defined recipient. If no recipient is defined, the email will be sent to the address defined in `$GLOBALS['TYPO3_CONF_VARS']['BE']['warning_email_addr']`.

![email.jpg](Documentation/Images/email.jpg)

## 🧑‍💻 Contributing

Please have a look at [`CONTRIBUTING.md`](CONTRIBUTING.md).

## ⭐ License

This project is licensed
under [GNU General Public License 2.0 (or later)](LICENSE.md).