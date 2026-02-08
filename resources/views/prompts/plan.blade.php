@@{!! $prdFilePath !!}
@if($planFilePath)
@@{!! $planFilePath !!}
@endif

Analyze this PRD and the current codebase to create a detailed implementation plan.

## Analysis Tasks

1. **Read the PRD** - Understand the functional requirements
2. **Analyze database schema** - Review migrations and models to understand current data structures
3. **Review composer.json** - Check available packages and dependencies
4. **Examine frontend stack** - Look at package.json, identify Vue/React/Blade patterns
5. **Identify existing patterns** - Find similar features to follow as examples

## Output

Create the file `{!! $planFilePath ?? str_replace('PRD.md', 'IMPLEMENTATION_PLAN.md', $prdFilePath) !!}` with this structure:

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
