# Agentic Ralph Wiggum loops for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/satoved/lararalph.svg?style=flat-square)](https://packagist.org/packages/satoved/lararalph)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/satoved/lararalph/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/satoved/lararalph/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/satoved/lararalph/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/satoved/lararalph/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/satoved/lararalph.svg?style=flat-square)](https://packagist.org/packages/satoved/lararalph)

Agentic Claude Code loops for Laravel. Provides Artisan commands to run Claude Code agents in autonomous loops, manage worktrees for parallel development, and scaffold specs-driven workflows.

## Installation

You can install the package via composer:

```bash
composer require satoved/lararalph
```

### Publish assets

Publish the config file and specs/claude assets in one go:

```bash
php artisan vendor:publish --tag="lararalph-config"
php artisan vendor:publish --tag="lararalph-specs"
```

**Config** (`config/lararalph.php`) -- IDE, worktree setup commands, and Claude settings:

```php
return [
    'ide' => 'open -na "PhpStorm.app" --args {path}', // or 'code {path}' for VSCode

    'worktree' => [
        'setup_commands' => ['composer install', 'npm install', 'herd secure'],
    ],

    'claude' => [
        'settings' => [
            'defaultMode' => 'acceptEdits',
            'enableAllProjectMcpServers' => true,
            'sandbox' => [
                'enabled' => true,
                'autoAllowBashIfSandboxed' => true,
            ],
        ],
    ],
];
```

**Specs** (`specs/`) -- directory structure for backlog/complete spec files used by agent loops.

**.claude/** -- Claude Code project settings and skills (e.g. PRD skill).

### Available commands

| Command | Description |
|---|---|
| `php artisan agent:loop` | Run a Claude Code agent in an autonomous loop |
| `php artisan agent:plan` | Plan an agent task |
| `php artisan agent:status` | Show status of running agents |
| `php artisan agent:kill` | Kill a running agent |
| `php artisan agent:tail` | Tail agent output |
| `php artisan worktree:setup` | Set up a git worktree for parallel development |
| `php artisan worktree:ide` | Open a worktree in your configured IDE |

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Oleg Makedonsky](https://github.com/satoved)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
