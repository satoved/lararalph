---
name: prd
description: Create a new PRD (Product Requirements Document) for handoff to a developer. Use when user wants to spec out a new feature.
user-invocable: true
---

# Create PRD

Create a PRD (Product Requirements Document) for handoff to a developer.

## Usage

```
/prd <feature description>
/prd
```

If no description is provided, ask clarifying questions to understand the feature.

## Behavior

1. **Gather Requirements**: Ask clarifying questions to fully understand the feature scope
2. **Research Codebase**: Explore relevant existing code, models, and patterns
3. **Create PRD Directory**: Create a new folder in `prd/_to_refine/` with a kebab-case name
4. **Write project.md**: Document the feature requirements
5. **Create progress.md**: Initialize an empty progress tracking file

## PRD Directory Structure

```
prd/
├── _to_refine/              # Need refinement
│   └── feature-name/
│       ├── project.md    # Full feature specification
│       └── progress.md   # Developer progress tracking (empty initially)
├── backlog/              # Active/pending PRDs
│   └── feature-name/
│       ├── project.md    # Full feature specification
│       └── progress.md   # Developer progress tracking (empty initially)
└── complete/             # Finished PRDs (moved here when done)
    └── old-feature/
        ├── project.md
        └── progress.md
```

New PRDs are always created in `prd/_to_refine/`. When a PRD is refined, it will be added to `prd/backlog` manually.
When a PRD is finished it will move to `prd/complete`

## project.md Template

```markdown
# Feature Name

## Overview
Brief 2-3 sentence description of what this feature does and why it's needed.

## Goals
- Primary goal
- Secondary goals

## User Stories
- As a [role], I want [feature] so that [benefit]

## Requirements

### Functional Requirements
1. Requirement with clear acceptance criteria
2. ...

### Non-Functional Requirements
- Performance considerations
- Security requirements
- Accessibility needs

## Technical Approach

### Affected Areas
- Models: List affected/new models
- Controllers: List affected/new controllers
- Frontend: List affected/new components
- Routes: New API endpoints or page routes

### Database Changes
- New tables or columns
- Migrations needed

### API Endpoints
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST   | /api/... | Create...   |

### Frontend Components
- New Vue components needed
- Existing components to modify

### Testing strategies
- Browser testing requirements
- Unit testing testing requirements

## UI/UX

### User Flow
1. Step-by-step user interaction

### Mockups/Wireframes
(Link or description of visual design if available)

## Edge Cases
- Edge case 1 and how to handle it
- Edge case 2 and how to handle it

## Out of Scope
- Features explicitly NOT included in this phase

## Open Questions
- [ ] Question that needs answering before implementation

## Dependencies
- External services or features this depends on

## Testing Strategy
- Unit tests needed
- Integration tests needed
- Manual testing scenarios

## Implementation Tasks (Prioritized)

Tasks ordered by priority. Check off as completed.

### High Priority
- [ ] Task 1 - Most critical path item
- [ ] Task 2 - Required for core functionality

### Medium Priority
- [ ] Task 3 - Important but not blocking
- [ ] Task 4 - Enhances the feature

### Low Priority
- [ ] Task 5 - Nice to have
- [ ] Task 6 - Polish/cleanup
```

## progress.md Template

Create an empty file.

## Guidelines

1. **Be Specific**: Vague requirements lead to misaligned implementations
2. **Include Context**: Explain WHY, not just WHAT
3. **Reference Existing Code**: Point to similar patterns in the codebase
4. **Consider Edge Cases**: Think through error states and unusual inputs
5. **Define Success**: Clear acceptance criteria for each requirement
6. **Keep Scope Bounded**: Explicitly state what's out of scope
7. **Use Project Conventions**: Follow patterns from CLAUDE.md (hash IDs, axios, etc.)

## Examples

### Example: Creating a PRD for a new notification feature

```
/prd Add push notifications for order status updates
```

Claude will:
1. Ask about notification types, triggers, and user preferences
2. Research existing notification code and order status handling
3. Create `prd/_to_refine/order-push-notifications/`
4. Write comprehensive project.md with technical approach
5. Create empty progress.md

### Example: Interactive PRD creation

```
/prd
```

Claude will prompt: "What feature would you like to create a PRD for?"

Then ask follow-up questions like:
- "Who is the target user for this feature?"
- "What problem does this solve?"
- "Are there any existing similar features in the codebase?"