#!/bin/bash

# Resolve PRD path - checks backlog first, then complete
resolve_prd_path() {
    local project="$1"
    if [ -d "prd/backlog/$project" ]; then
        echo "prd/backlog/$project"
    elif [ -d "prd/complete/$project" ]; then
        echo "prd/complete/$project"
    else
        echo ""
    fi
}

if [ -z "$1" ]; then
    # Get list of projects from prd/backlog directory (prioritize backlog)
    projects=($(ls -d prd/backlog/*/ 2>/dev/null | xargs -I {} basename {}))

    if [ ${#projects[@]} -eq 0 ]; then
        echo "No projects found in prd/backlog/ directory"
        exit 1
    fi

    echo "Select a project:"
    select PROJECT in "${projects[@]}"; do
        if [ -n "$PROJECT" ]; then
            break
        else
            echo "Invalid selection. Please try again."
        fi
    done
    PRD_PATH="prd/backlog/$PROJECT"
else
    PROJECT="$1"
    PRD_PATH=$(resolve_prd_path "$PROJECT")
    if [ -z "$PRD_PATH" ]; then
        echo "Error: Project not found in prd/backlog/ or prd/complete/: $PROJECT"
        exit 1
    fi
fi

PRD_FILE="$PRD_PATH/project.md"
PROGRESS_FILE="$PRD_PATH/progress.md"

if [ ! -f "$PRD_FILE" ]; then
    echo "Error: PRD file not found: $PRD_FILE"
    exit 1
fi

if [ ! -f "$PROGRESS_FILE" ]; then
    echo "Error: Progress file not found: $PROGRESS_FILE"
    exit 1
fi

claude --permission-mode acceptEdits "@$PRD_FILE @$PROGRESS_FILE \
1. Read the PRD and progress file. \
2. Find the next incomplete task and implement it. \
3. Commit your changes. \
4. Update progress.md with what you did. \
ONLY DO ONE TASK AT A TIME."

