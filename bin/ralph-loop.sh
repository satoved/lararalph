#!/bin/bash
set -e

if [ -z "$1" ] || [ -z "$2" ]; then
    echo "Usage: $0 <project-name> <iterations>"
    echo "Example: $0 project 20"
    exit 1
fi

PROJECT="$1"
ITERATIONS="$2"

# Resolve PRD path - checks backlog first, then complete
if [ -d "prd/backlog/$PROJECT" ]; then
    PRD_PATH="prd/backlog/$PROJECT"
elif [ -d "prd/complete/$PROJECT" ]; then
    PRD_PATH="prd/complete/$PROJECT"
else
    echo "Error: Project not found in prd/backlog/ or prd/complete/: $PROJECT"
    exit 1
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

for ((i=1; i<=$ITERATIONS; i++)); do
  result=$(claude --permission-mode acceptEdits -p "@$PRD_FILE @$PROGRESS_FILE \
  1. Find the highest-priority task and implement it. \
  2. Run your tests and type checks. \
  3. Update the PRD with what was done. \
  4. Append your progress to progress.md and add checkboxes where needed in project.md. \
  5. Commit and push your changes. \
  ONLY WORK ON A SINGLE TASK. \
  If the PRD is complete, output <promise>COMPLETE</promise>.")

  echo "$result"

  if [[ "$result" == *"<promise>COMPLETE</promise>"* ]]; then
    echo "PRD complete after $i iterations."
    exit 0
  fi
done

