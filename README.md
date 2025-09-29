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

- **new ip address** based warning mode

> [!WARNING]
> This package is in early development and may change significantly.

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

Add a warning detector in your `ext_localconf.php`:

```php
use MoveElevator\Typo3LoginWarning\Configuration;
use MoveElevator\Typo3LoginWarning\Notification\EmailNotification;
use MoveElevator\Typo3LoginWarning\Detector\NewIpDetector;
use MoveElevator\Typo3LoginWarning\Detector\LongTimeNoSeeDetector;

// Simple configuration
// (EmailNotification will be used with "warning_email_addr" configuration)
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][Configuration::EXT_KEY]['detector'] = [
    NewIpDetector::class,
    LongTimeNoSeeDetector::class
];

// Extended example configuration
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][Configuration::EXT_KEY]['detector'] = [
    NewIpDetector::class => [
        'hashIpAddress' => false,
        'fetchGeolocation' => false,
        'whitelist' => [
            '192.168.97.5',
        ],
        'notification' => [
            EmailNotification::class => [
                'recipient' => 'test123@test.de',
            ],
        ],
    ],
    LongTimeNoSeeDetector::class => [
        'thresholdDays' => 180,
        'notification' => [
            EmailNotification::class => [
                'recipient' => 'admin@example.com',
            ],
        ],
    ],
];
```

## 💡 Concepts

### Detectors

Detectors are used to detect certain login events. If a detector matches, a notification will be sent.

The following detectors are available:

- `NewIpDetector`: Detects logins from new IP addresses and triggers a warning email. The IP address will be stored and can be hashed for privacy reasons. You can also define a whitelist of IP addresses that will not trigger a warning. An ip geolocation lookup can be enabled to add more information to the notification email.
- `LongTimeNoSeeDetector`: Detects logins after a long period of inactivity (default: 365 days).

![email.jpg](Documentation/Images/email.jpg)

> [!TIP]
> You can implement your own detector by implementing the `MoveElevator\Typo3LoginWarning\Detector\DetectorInterface`.

### Notification

The following notification methods are available:

- `EmailNotification`: Sends a warning email to a defined recipient.

> [!TIP]
> You can implement more notification methods by implementing the `MoveElevator\Typo3LoginWarning\Notification\NotificationInterface`.

## 🧑‍💻 Contributing

Please have a look at [`CONTRIBUTING.md`](CONTRIBUTING.md).

## ⭐ License

This project is licensed
under [GNU General Public License 2.0 (or later)](LICENSE.md).