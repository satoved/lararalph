# Lararalph

Laravel package implementing agentic Ralph Wiggum loops — a three-phase workflow (PRD → Plan → Build) where Claude runs in loops with fresh context windows to implement features from specifications.

**Package:** `satoved/lararalph` | **PHP 8.4+** | **Laravel 11–12** | **Pest v4** | **Orchestra Testbench**

## Quick Reference

```bash
composer test              # Run all tests (Pest)
composer test -- --filter=SpecResolver  # Run specific test
composer format            # Fix code style (Pint)
composer format -- --test  # Check code style without fixing
```

## Project Structure

```
src/
├── AgentRunner.php                  # Executes bin/ralph-loop.js (Node.js orchestrator)
├── LararalphServiceProvider.php     # Registers commands, binds SpecResolver, publishes assets
├── FileSpecResolver.php             # Finds specs in specs/backlog/ and specs/complete/
├── Commands/
│   ├── PlanCommand.php              # ralph:plan {spec?} {--force} {--create-worktree}
│   ├── BuildCommand.php             # ralph:build {spec?} {--iterations=30} {--create-worktree}
│   └── FinishCommand.php            # ralph:finish — remove git worktrees
├── Contracts/
│   ├── Spec.php                     # Readonly DTO: name, absoluteFolderPath, absolutePrdFilePath
│   └── SpecResolver.php             # Interface: getBacklogSpecs(), resolve(), choose()
└── Worktree/
    ├── WorktreeCreator.php          # Creates git worktrees with configurable setup pipeline
    └── Steps/                       # WorktreeSetupStep implementations (config-driven)
        ├── CopyEnvFile.php          # Transforms APP_URL with spec name
        ├── RunInstallComposer.php
        ├── RunInstallNpm.php
        ├── RunHerdSecure.php
        └── OpenInPHPStorm.php

tests/                               # Mirrors src/ structure
├── ...
config/lararalph.php                 # Worktree setup steps array
resources/views/prompts/             # Blade templates to publish prompts for editing
bin/ralph-loop.js                    # Node.js script managing agent loop iterations
```