# Lararalph

[![Latest Version on Packagist](https://img.shields.io/packagist/v/satoved/lararalph.svg?style=flat-square)](https://packagist.org/packages/satoved/lararalph)
[![Total Downloads](https://img.shields.io/packagist/dt/satoved/lararalph.svg?style=flat-square)](https://packagist.org/packages/satoved/lararalph)

Laravel package that wraps the [Ralph Wiggum technique](https://ghuntley.com/ralph/) into Artisan commands and Laravel-grade UX. Define requirements, plan implementation, build — through a specs-driven workflow with Claude Code doing the work in agentic loops.

## What is the Ralph Wiggum Technique?

The [Ralph Wiggum technique](https://ghuntley.com/ralph/) is a development methodology where Claude runs in an agentic loop: given a prompt, it reads your codebase, reasons about what to do, writes code, runs tests, and iterates until the task is complete. Each iteration gets a fresh context window. The implementation plan file on disk acts as shared state between iterations.

The core idea: **three phases, two prompts, one loop.**

1. **Define requirements** — human + Claude conversation produces specs
2. **Plan** — Claude analyzes specs vs existing code (gap analysis), produces a prioritized task list
3. **Build** — Claude picks one task per iteration, implements, tests, commits, loop restarts with fresh context

## Workflow

```
 claude /prd            artisan ralph:plan         artisan ralph:build
      │                         │                           │
      ▼                         ▼                           ▼
    PRD.md ──────────► IMPLEMENTATION_PLAN.md ──────────► Code
(requirements)         (prioritized checklist)     (one task at a time)
```

## Specs Directory

The package manages a `specs/` directory in your project root:

```
specs/
├── backlog/
│   └── 2026-01-15-user-notifications/
│       ├── PRD.md                    # Functional requirements (created by `claude /prd`)
│       ├── IMPLEMENTATION_PLAN.md    # Prioritized task list (created by `artisan ralph:plan`)
│       └── logs/                     # Agent loop JSON + text logs
└── complete/
    └── 2026-01-14-old-feature/       # Moved here when done
```

- `claude /prd` creates a timestamped folder in `specs/backlog/` with `PRD.md`
- `artisan ralph:plan` reads `PRD.md` + existing codebase, produces `IMPLEMENTATION_PLAN.md`
- `artisan ralph:build` picks the highest-priority unchecked task, implements it, runs tests, commits — one task per loop iteration
- Finished specs move to `specs/complete/`

## Requirements

- PHP 8.4+, Laravel 11+
- Node.js installed
- [Claude Code CLI](https://docs.anthropic.com/en/docs/claude-code)

## Installation

```bash
composer require satoved/lararalph
```

Publish config, specs directory, and Claude skill:

```bash
php artisan vendor:publish --tag="lararalph-config"
php artisan vendor:publish --tag="lararalph-specs"
php artisan vendor:publish --tag="lararalph-claude"
```

## Usage

### 1. Define Requirements

Inside Claude Code, use the `/prd` skill:

```
claude "/prd user notifications"
```

Claude asks clarifying questions about the feature, then writes `specs/backlog/2026-01-15-user-notifications/PRD.md`. The PRD focuses on *what* (functional requirements, acceptance criteria, user stories) — no implementation details.

### 2. Plan

```bash
# Interactive: choose from backlog specs
php artisan ralph:plan

# Or specify directly:
php artisan ralph:plan 2026-01-15-user-notifications
```

Claude studies your codebase against the PRD using subagents, performs gap analysis, and produces `IMPLEMENTATION_PLAN.md` — a prioritized bullet-point checklist of tasks to implement.

The plan is disposable. If it's wrong, regenerate it with `--force`. One planning loop is cheap compared to building from a bad plan.

### 3. Build

```bash
# Interactive: choose from backlog specs
php artisan ralph:build

# Or specify directly:
php artisan ralph:build 2026-01-15-user-notifications
```

Each loop iteration: Claude reads the plan, picks the most important unchecked task, implements it, runs tests, updates the plan, and commits. Then the loop restarts with a fresh context window and Claude picks the next task.

## Worktrees

Use `--worktree` on `ralph:build` to run in an isolated git worktree:

```bash
php artisan ralph:build 2026-01-15-user-notifications --worktree
```

This creates a sibling directory (e.g., `../myapp-2026-01-15-user-notifications/`), copies `.env` with adjusted URLs, and runs setup commands (`composer install`, etc).

```bash
# Clean up worktrees
php artisan ralph:finish
```

## Configuration

```bash
php artisan vendor:publish --tag="lararalph-config"
```

`config/lararalph.php`:

```php
return [
    'worktree_setup' => [
        RunInstallComposer::class,
        RunInstallNpm::class,
        RunHerdSecure::class,
        OpenInPHPStorm::class,
    ],
];
```

### Customizing Prompts

Planning and building prompts are Blade templates. Publish them:

```bash
php artisan vendor:publish --tag="lararalph-views"
```

Edit `resources/views/vendor/lararalph/prompts/plan.blade.php` and `build.blade.php` to adjust how Claude approaches planning and building. This is where you steer Ralph — add guardrails, change subagent counts, adjust backpressure instructions.

### Customizing the PRD Skill

Published to `.claude/skills/prd/SKILL.md`. Edit to match your team's PRD template.

## Commands

| Command                  | Description |
|--------------------------|---|
| `ralph:plan {feature?}`  | Gap analysis: specs vs codebase → `IMPLEMENTATION_PLAN.md` |
| `ralph:build {project?}` | Pick task, implement, test, commit, loop |
| `ralph:finish`           | List and remove git worktrees |

## Testing

```bash
composer test
```

## Credits

- [Geoffrey Huntley](https://ghuntley.com/ralph/) — the Ralph Wiggum technique
- [Oleg Makedonsky](https://github.com/satoved)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
