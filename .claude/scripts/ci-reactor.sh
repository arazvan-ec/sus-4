#!/usr/bin/env bash
# ci-reactor.sh — Diagnose and fix CI failures automatically
#
# Usage:
#   ./.claude/scripts/ci-reactor.sh <ci-log-file-or-url>

set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
PROJECT_CONTEXT="$PROJECT_ROOT/_bmad-output/project-context.md"

if [[ $# -lt 1 ]]; then
    echo "Usage: $0 <ci-log-file-or-url>"
    exit 1
fi

CI_LOG_SOURCE="$1"

if [[ -f "$CI_LOG_SOURCE" ]]; then
    CI_LOG_CONTENT="$(cat "$CI_LOG_SOURCE")"
elif [[ "$CI_LOG_SOURCE" == http* ]]; then
    CI_LOG_CONTENT="$(curl -sL "$CI_LOG_SOURCE" 2>/dev/null || echo "ERROR: Could not fetch $CI_LOG_SOURCE")"
else
    echo "ERROR: CI log not found: $CI_LOG_SOURCE"
    exit 1
fi

PROMPT="You are a CI/CD diagnostic agent. Analyze the CI failure log, classify the issue, and fix it if possible.

## RULES
- Classify confidence: HIGH, MEDIUM, LOW.
- Only commit if HIGH confidence and tests pass.
- After fixing, run php bin/phpunit to verify.

## PROJECT CONTEXT
$(cat "$PROJECT_CONTEXT" 2>/dev/null || echo "No project context available.")

## CI FAILURE LOG
$CI_LOG_CONTENT

## TASK
1. Identify failures.
2. Classify each: type and confidence.
3. HIGH: fix, test, commit. MEDIUM/LOW: report only.
"

echo "=== CI Reactor ==="
echo "Source: $CI_LOG_SOURCE"
echo "==================="
echo ""

claude -p "$PROMPT" --allowedTools "Read,Write,Edit,Bash,Glob,Grep"
