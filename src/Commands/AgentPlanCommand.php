<?php

namespace Satoved\Lararalph\Commands;

use Illuminate\Console\Command;

use function Laravel\Prompts\search;

class AgentPlanCommand extends Command
{
    protected $signature = 'ralph:plan
                            {feature? : The feature name to plan (interactive if not provided)}
                            {--force : Regenerate IMPLEMENTATION_PLAN.md even if it exists}';

    protected $description = 'Create an implementation plan for a PRD by analyzing the codebase';

    public function handle()
    {
        $feature = $this->argument('feature');
        $force = $this->option('force');

        // If no feature specified, let user choose from available specs
        if (!$feature) {
            $feature = $this->chooseFeature();
            if (!$feature) {
                return 1;
            }
        }

        // Resolve the spec path (supports both full folder name and partial match)
        $specPath = $this->resolveSpecPath($feature);
        if (!$specPath) {
            $this->error("Spec not found: {$feature}");
            $this->info("Run '/prd' to create a new spec first.");
            return 1;
        }

        $prdFile = $specPath . '/PRD.md';
        $planFile = $specPath . '/IMPLEMENTATION_PLAN.md';

        // Validate PRD.md exists
        if (!file_exists($prdFile)) {
            $this->error("PRD.md not found at: {$prdFile}");
            return 1;
        }

        // Check if IMPLEMENTATION_PLAN.md already exists
        if (file_exists($planFile) && !$force) {
            $this->error("IMPLEMENTATION_PLAN.md already exists at: {$planFile}");
            $this->info("Use --force to regenerate.");
            return 1;
        }

        $this->info("Creating implementation plan for: " . basename($specPath));
        $this->newLine();

        // Build the planning prompt
        $prompt = $this->buildPlanningPrompt($specPath);

        // Run Claude to generate the plan
        return $this->runClaude($prompt, $specPath);
    }

    protected function chooseFeature(): ?string
    {
        $this->info("Fetching available specs...");

        $specs = $this->getSpecs();

        if (empty($specs)) {
            $this->error("No specs found in specs/backlog/");
            $this->info("Run '/prd' to create a new spec first.");
            return null;
        }

        // Use array_combine so search() returns the spec name, not the index
        $specs = array_combine($specs, $specs);

        return search(
            label: 'Select a spec to plan',
            options: fn (string $value) => strlen($value) > 0
                ? array_filter($specs, fn ($s) => str_contains(strtolower($s), strtolower($value)))
                : $specs,
        );
    }

    protected function getSpecs(): array
    {
        $specsDir = getcwd() . '/specs/backlog';
        if (!is_dir($specsDir)) {
            return [];
        }

        return array_values(array_filter(
            scandir($specsDir),
            fn ($dir) => $dir !== '.' && $dir !== '..' && is_dir("{$specsDir}/{$dir}")
        ));
    }

    protected function resolveSpecPath(string $feature): ?string
    {
        $backlogDir = getcwd() . '/specs/backlog';
        $completeDir = getcwd() . '/specs/complete';

        // First, try exact match in backlog
        $exactPath = $backlogDir . '/' . $feature;
        if (is_dir($exactPath)) {
            return $exactPath;
        }

        // Try exact match in complete
        $exactPath = $completeDir . '/' . $feature;
        if (is_dir($exactPath)) {
            return $exactPath;
        }

        // Try partial match (search for feature name after date prefix)
        if (is_dir($backlogDir)) {
            foreach (scandir($backlogDir) as $dir) {
                if ($dir === '.' || $dir === '..') continue;
                // Match if the feature name appears after the date prefix
                if (preg_match('/^\d{4}-\d{2}-\d{2}-(.+)$/', $dir, $matches)) {
                    if ($matches[1] === $feature || str_contains($dir, $feature)) {
                        return $backlogDir . '/' . $dir;
                    }
                }
            }
        }

        if (is_dir($completeDir)) {
            foreach (scandir($completeDir) as $dir) {
                if ($dir === '.' || $dir === '..') continue;
                if (preg_match('/^\d{4}-\d{2}-\d{2}-(.+)$/', $dir, $matches)) {
                    if ($matches[1] === $feature || str_contains($dir, $feature)) {
                        return $completeDir . '/' . $dir;
                    }
                }
            }
        }

        return null;
    }

    protected function buildPlanningPrompt(string $specPath): string
    {
        $prdFile = $specPath . '/PRD.md';
        $planFile = $specPath . '/IMPLEMENTATION_PLAN.md';

        return <<<PROMPT
@{$prdFile}

Analyze this PRD and the current codebase to create a detailed implementation plan.

## Analysis Tasks

1. **Read the PRD** - Understand the functional requirements
2. **Analyze database schema** - Review migrations and models to understand current data structures
3. **Review composer.json** - Check available packages and dependencies
4. **Examine frontend stack** - Look at package.json, identify Vue/React/Blade patterns
5. **Identify existing patterns** - Find similar features to follow as examples

## Output

Create the file `{$planFile}` with this structure:

```markdown
# Implementation Plan: [Feature Name]

## Technical Overview
Brief description of how this feature will be implemented technically.

## Codebase Analysis

### Relevant Existing Code
- List existing files/patterns that this feature should follow
- Reference similar implementations for consistency

### Database Schema
- Current relevant tables and their relationships
- New tables/columns needed

### Available Packages
- Existing packages that can be leveraged
- Any new packages recommended

## Implementation Steps

### Phase 1: [Name] (Backend/Data)
- [ ] Task 1 - Specific file and what to create/modify
- [ ] Task 2 - Specific file and what to create/modify

### Phase 2: [Name] (API/Logic)
- [ ] Task 3 - Specific file and what to create/modify
- [ ] Task 4 - Specific file and what to create/modify

### Phase 3: [Name] (Frontend)
- [ ] Task 5 - Specific file and what to create/modify
- [ ] Task 6 - Specific file and what to create/modify

### Phase 4: Testing & Polish
- [ ] Task 7 - Test coverage requirements
- [ ] Task 8 - Final integration testing

## API Endpoints
| Method | Endpoint | Description | Request | Response |
|--------|----------|-------------|---------|----------|
| POST   | /api/... | Description | {...}   | {...}    |

## Frontend Components
- Component name - Purpose and location
- Component name - Purpose and location

## Testing Strategy
- Unit tests: What to test
- Feature tests: Key scenarios
- Browser tests: User flows to verify

## Risks & Considerations
- Potential issues and how to mitigate them
- Performance considerations
- Security considerations

## Notes
- Any additional context or decisions made during planning
```

Be thorough but practical. Each task should be specific enough that a developer knows exactly what file to create or modify.
PROMPT;
    }

    protected function runClaude(string $prompt, string $specPath): int
    {
        $this->info("Running Claude to analyze codebase and create plan...");
        $this->newLine();

        $settings = config('lararalph.claude.settings', []);
        $settingsJson = json_encode($settings, JSON_UNESCAPED_SLASHES);

        $command = sprintf(
            'claude --settings %s -p %s',
            escapeshellarg($settingsJson),
            escapeshellarg($prompt)
        );

        passthru($command, $exitCode);

        if ($exitCode === 0) {
            $this->newLine();
            $planFile = $specPath . '/IMPLEMENTATION_PLAN.md';
            if (file_exists($planFile)) {
                $this->info("Implementation plan created: " . $planFile);
            } else {
                $this->warn("Claude completed but IMPLEMENTATION_PLAN.md was not created.");
                $this->info("You may need to run the command again or create it manually.");
            }
        }

        return $exitCode;
    }
}
