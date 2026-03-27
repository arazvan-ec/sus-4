#!/usr/bin/env bash
# worktree-exec.sh — Execute a task in an isolated git worktree
#
# Usage:
#   ./.claude/scripts/worktree-exec.sh <task-file> [extra-context-files...]

set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
TASK_FILE="$1"

if [[ $# -lt 1 ]]; then
    echo "Usage: $0 <task-file> [extra-context-files...]"
    exit 1
fi

if [[ ! -f "$TASK_FILE" ]]; then
    echo "ERROR: Task file not found: $TASK_FILE"
    exit 1
fi

TASK_NAME="$(basename "$TASK_FILE" .md)"
BRANCH_NAME="worktree/${TASK_NAME}-$(date +%Y%m%d-%H%M%S)"
WORKTREE_DIR="/tmp/worktree-${TASK_NAME}-$$"

echo "=== Worktree Exec ==="
echo "Task: $TASK_FILE"
echo "Branch: $BRANCH_NAME"
echo "Worktree: $WORKTREE_DIR"
echo "====================="
echo ""

git worktree add -b "$BRANCH_NAME" "$WORKTREE_DIR" HEAD

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
(
    cd "$WORKTREE_DIR"
    "$SCRIPT_DIR/fresh-exec.sh" "$@"
)
EXIT_CODE=$?

COMMIT_COUNT=$(cd "$WORKTREE_DIR" && git log --oneline "HEAD...HEAD~10" 2>/dev/null | head -10 | wc -l)
echo ""
echo "=== Worktree Results ==="
echo "Branch: $BRANCH_NAME"
echo "Commits: ~$COMMIT_COUNT"
echo "Worktree: $WORKTREE_DIR"
echo ""

if [[ $COMMIT_COUNT -eq 0 ]]; then
    echo "No commits made. Cleaning up worktree."
    git worktree remove "$WORKTREE_DIR" 2>/dev/null || true
    git branch -d "$BRANCH_NAME" 2>/dev/null || true
else
    echo "To review:  cd $WORKTREE_DIR && git log --oneline"
    echo "To merge:   git merge $BRANCH_NAME"
    echo "To cleanup: git worktree remove $WORKTREE_DIR && git branch -d $BRANCH_NAME"
fi

exit $EXIT_CODE
