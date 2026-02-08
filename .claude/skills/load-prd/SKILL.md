---
name: load-prd
description: Load PRD context for manual iteration. Use when user wants to work on or review an existing PRD.
user-invocable: true
---

# Load PRD Context

Load the project.md and progress.md files from a PRD into context for manual iteration.

## Usage

```
/load-prd <prd-name>
/load-prd
```

If no PRD name is provided, list available PRDs and ask which one to load.

## Directory Structure

PRDs are organized into two directories:
- `prd/backlog/` - Active/pending PRDs (prioritized)
- `prd/complete/` - Finished PRDs

## Behavior

1. **Without argument**: List all PRDs in `prd/backlog/` directory first (these are prioritized), then show completed PRDs from `prd/complete/` if helpful
2. **With argument**: Search for the PRD in `prd/backlog/` first, then `prd/complete/`

## What to Load

Read and present the following files from the resolved PRD path:
- `project.md` - The full feature specification
- `progress.md` - The current implementation progress

## Output Format

After loading, present the contents clearly:

```
## PRD: <prd-name> (from backlog|complete)

### project.md
<contents of project.md>

### progress.md
<contents of progress.md>
```

## Guidelines

1. If the specified PRD doesn't exist in either directory, show available PRDs from both directories
2. Always prioritize `prd/backlog/` when listing or searching
3. If progress.md is empty, note that no progress has been tracked yet
4. After loading, be ready to discuss, iterate, or update the PRD based on user feedback
5. When the user makes changes to requirements, offer to update the project.md
6. When tracking implementation progress, offer to update progress.md