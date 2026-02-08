#!/bin/bash
set -e

if [ -z "$1" ] || [ -z "$2" ]; then
    echo "Usage: $0 <spec-name> <iterations>"
    echo "Example: $0 2024-01-15-feature-name 20"
    exit 1
fi

PROJECT="$1"
ITERATIONS="$2"

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

SPEC_PATH=$(resolve_spec_path "$PROJECT")

if [ -z "$SPEC_PATH" ]; then
    echo "Error: Spec not found in specs/backlog/ or specs/complete/: $PROJECT"
    exit 1
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

for ((i=1; i<=$ITERATIONS; i++)); do
  result=$(claude --permission-mode acceptEdits -p "@$PRD_FILE @$PLAN_FILE \
  1. Find the highest-priority unchecked task in IMPLEMENTATION_PLAN.md and implement it. \
  2. Run your tests and type checks. \
  3. Mark the task as complete in IMPLEMENTATION_PLAN.md by checking its checkbox. \
  4. Commit and push your changes. \
  ONLY WORK ON A SINGLE TASK. \
  If all tasks in IMPLEMENTATION_PLAN.md are complete, output <promise>COMPLETE</promise>.")

  echo "$result"

  if [[ "$result" == *"<promise>COMPLETE</promise>"* ]]; then
    echo "Implementation complete after $i iterations."
    exit 0
  fi
done
