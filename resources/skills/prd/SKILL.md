---
name: prd
description: Create a new PRD (Product Requirements Document) with functional requirements only. Use when user wants to spec out a new feature.
user-invocable: true
---

# Create PRD

Create a PRD (Product Requirements Document) focused on functional requirements for handoff to a developer.

## Usage

```
/prd <feature description>
/prd
```

If no description is provided, ask clarifying questions to understand the feature.

## Behavior

1. **Gather Requirements**: Ask clarifying questions to fully understand the feature scope
2. **Research Codebase**: Explore relevant existing code, models, and patterns to understand context
3. **Create PRD Directory**: Create a new folder in `specs/backlog/` with timestamp prefix: `YYYY_MM_DD_feature-name`
4. **Write PRD.md**: Document the functional requirements (no technical implementation details)

## Directory Structure

```
specs/
├── backlog/
│   └── 2024-01-15-feature-name/
│       ├── PRD.md                    # Functional requirements (created by this skill)
│       ├── IMPLEMENTATION_PLAN.md    # Technical plan (created by ralph:plan later)
│       └── logs/                     # Agent loop logs
└── complete/
    └── 2024-01-15-old-feature/
        ├── PRD.md
        └── IMPLEMENTATION_PLAN.md
```

New PRDs are always created in `specs/backlog/` with a timestamp prefix (e.g., `2024-01-15-feature-name`).
When a PRD is finished, it gets moved to `specs/complete/`.

## PRD.md Template

```markdown
# Feature Name

## Problem Statement
What pain point are we solving? Who experiences this problem and when?

## Jobs To Be Done
What job is the user hiring this feature to do? Use the JTBD format:
- When [situation], I want to [motivation], so I can [expected outcome]

## User Stories
- As a [role], I want [feature] so that [benefit]
- As a [role], I want [feature] so that [benefit]

## Acceptance Criteria

### Must Have
1. Clear, testable criterion with specific behavior
2. Another testable criterion
3. ...

### Should Have
1. Important but not critical criteria
2. ...

### Nice to Have
1. Lower priority items
2. ...

## User Flow
1. Step-by-step description of user interaction
2. Include decision points and branches
3. Note error states and how to handle them

## Edge Cases
- Edge case 1 and expected behavior
- Edge case 2 and expected behavior

## Out of Scope
- Features explicitly NOT included in this phase
- Future enhancements to consider later

## Open Questions
- [ ] Question that needs answering before implementation
- [ ] Another question to clarify
```

## Guidelines

1. **Focus on WHAT, not HOW**: Describe desired behavior and outcomes, not implementation
2. **Be Specific**: Vague requirements lead to misaligned implementations
3. **Include Context**: Explain WHY this feature matters
4. **User-Centric Language**: Write from the user's perspective
5. **Testable Criteria**: Every acceptance criterion should be verifiable
6. **Keep Scope Bounded**: Explicitly state what's out of scope
7. **No Technical Details**: Don't include database schemas, API endpoints, or code - those would go in IMPLEMENTATION_PLAN.md

## Timestamp Format

Use `YYYY-MM-DD` format for the folder prefix:
- Good: `2024-01-15-user-notifications`
- Bad: `20240115-user-notifications` (no dashes in date)
- Bad: `user-notifications` (missing timestamp)

## Examples

### Example: Creating a PRD for a notification feature

```
/prd Add push notifications for order status updates
```

Claude will:
1. Ask about notification triggers, user preferences, and expected behavior
2. Research existing notification patterns in the codebase for context, focusing on frontend UX
3. Create `specs/backlog/2024-01-15-order-push-notifications/`
4. Write comprehensive PRD.md with acceptance criteria

### Example: Interactive PRD creation

```
/prd
```

Claude will prompt: "What feature would you like to create a PRD for?"

Then ask follow-up questions like:
- "What problem does this solve for users?"
- "Who is the target user for this feature?"
- "What does success look like for this feature?"
- "Are there any constraints or limitations to consider?"
