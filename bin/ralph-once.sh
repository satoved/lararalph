#!/bin/bash

# Resolve spec path - checks backlog first, then complete
# Supports both exact match and partial match (feature name without date prefix)
resolve_spec_path() {
    local project="$1"

    # Try exact match in backlog
    if [ -d "specs/backlog/$project" ]; then
        echo "specs/backlog/$project"
        return
    fi

    # Try exact match in complete
    if [ -d "specs/complete/$project" ]; then
        echo "specs/complete/$project"
        return
    fi

    # Try partial match in backlog (search for feature name after date prefix)
    for dir in specs/backlog/*/; do
        if [ -d "$dir" ]; then
            dirname=$(basename "$dir")
            # Check if dirname matches pattern YYYY_MM_DD_feature and contains the project name
            if [[ "$dirname" =~ ^[0-9]{4}-[0-9]{2}-[0-9]{2}-(.+)$ ]]; then
                feature_name="${BASH_REMATCH[1]}"
                if [ "$feature_name" = "$project" ] || [[ "$dirname" == *"$project"* ]]; then
                    echo "specs/backlog/$dirname"
                    return
                fi
            fi
        fi
    done

    # Try partial match in complete
    for dir in specs/complete/*/; do
        if [ -d "$dir" ]; then
            dirname=$(basename "$dir")
            if [[ "$dirname" =~ ^[0-9]{4}-[0-9]{2}-[0-9]{2}-(.+)$ ]]; then
                feature_name="${BASH_REMATCH[1]}"
                if [ "$feature_name" = "$project" ] || [[ "$dirname" == *"$project"* ]]; then
                    echo "specs/complete/$dirname"
                    return
                fi
            fi
        fi
    done

    echo ""
}

if [ -z "$1" ]; then
    # Get list of specs from specs/backlog directory (prioritize backlog)
    projects=($(ls -d specs/backlog/*/ 2>/dev/null | xargs -I {} basename {}))

    if [ ${#projects[@]} -eq 0 ]; then
        echo "No specs found in specs/backlog/ directory"
        echo "Run '/prd' to create a new spec first."
        exit 1
    fi

    echo "Select a spec:"
    select PROJECT in "${projects[@]}"; do
        if [ -n "$PROJECT" ]; then
            break
        else
            echo "Invalid selection. Please try again."
        fi
    done
    SPEC_PATH="specs/backlog/$PROJECT"
else
    PROJECT="$1"
    SPEC_PATH=$(resolve_spec_path "$PROJECT")
    if [ -z "$SPEC_PATH" ]; then
        echo "Error: Spec not found in specs/backlog/ or specs/complete/: $PROJECT"
        exit 1
    fi
fi

PRD_FILE="$SPEC_PATH/PRD.md"
PLAN_FILE="$SPEC_PATH/IMPLEMENTATION_PLAN.md"

if [ ! -f "$PRD_FILE" ]; then
    echo "Error: PRD.md not found: $PRD_FILE"
    exit 1
fi

if [ ! -f "$PLAN_FILE" ]; then
    echo "Error: IMPLEMENTATION_PLAN.md not found: $PLAN_FILE"
    echo ""
    echo "Run 'php artisan ralph:plan $PROJECT' first to create an implementation plan."
    exit 1
fi

CLAUDE_SETTINGS="${2:-}"

CLAUDE_ARGS=()
if [ -n "$CLAUDE_SETTINGS" ]; then
    CLAUDE_ARGS+=(--settings "$CLAUDE_SETTINGS")
else
    CLAUDE_ARGS+=(--permission-mode acceptEdits)
fi

claude "${CLAUDE_ARGS[@]}" "@$PRD_FILE @$PLAN_FILE \
1. Read the PRD and implementation plan. \
2. Find the next unchecked task in IMPLEMENTATION_PLAN.md and implement it. \
3. Commit your changes. \
4. Mark the task as complete by checking its checkbox in IMPLEMENTATION_PLAN.md. \
ONLY DO ONE TASK AT A TIME."
