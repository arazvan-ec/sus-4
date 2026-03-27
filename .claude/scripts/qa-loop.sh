#!/usr/bin/env bash
# qa-loop.sh — Iterative QA evaluation loop (Developer <-> Evaluator)
#
# Usage:
#   ./.claude/scripts/qa-loop.sh <task-file> [max-iterations]

set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
FRESH_DEV="$SCRIPT_DIR/fresh-exec.sh"
PROJECT_CONTEXT="$PROJECT_ROOT/_bmad-output/project-context.md"
ARCHITECTURE="$PROJECT_ROOT/_bmad-output/planning-artifacts/architecture.md"
QA_SKILL="$PROJECT_ROOT/.claude/skills/bmad-agent-qa/SKILL.md"

if [[ $# -lt 1 ]]; then
    echo "Usage: $0 <task-file> [max-iterations]"
    exit 1
fi

STORY_FILE="$1"
MAX_ITERATIONS="${2:-3}"

if [[ ! -f "$STORY_FILE" ]]; then
    echo "ERROR: Task file not found: $STORY_FILE"
    exit 1
fi

if [[ ! -f "$QA_SKILL" ]]; then
    echo "ERROR: QA skill not found: $QA_SKILL"
    echo "Ensure BMAD QA agent is installed."
    exit 1
fi

STORY_NAME="$(basename "$STORY_FILE" .md)"
LOG_DIR="/tmp/qa-loop-$STORY_NAME-$(date +%Y%m%d-%H%M%S)"
mkdir -p "$LOG_DIR"

echo "=== QA Loop ==="
echo "Task: $STORY_FILE"
echo "Max iterations: $MAX_ITERATIONS"
echo "Logs: $LOG_DIR"
echo "================"
echo ""

echo ">>> Iteration 0: Initial implementation"
"$FRESH_DEV" "$STORY_FILE" > "$LOG_DIR/dev-iteration-0.log" 2>&1
echo "Implementation complete."
echo ""

build_qa_prompt() {
    local iteration="$1"
    local previous_report="${2:-}"
    local prompt="You are a QA engineer. Find problems, not approve.\n\n## QA GUIDELINES\n$(cat "$QA_SKILL")\n\n## PROJECT CONTEXT\n$(cat "$PROJECT_CONTEXT")\n\n## TASK BEING EVALUATED\n$(cat "$STORY_FILE")\n"
    if [[ -n "$previous_report" && -f "$previous_report" ]]; then
        prompt+="\n## PREVIOUS QA REPORT\n$(cat "$previous_report")\nVerify ALL previously reported bugs are fixed.\n"
    fi
    prompt+="\n## YOUR TASK\n1. Run php bin/phpunit.\n2. Review implementation against acceptance criteria.\n3. Score each criterion 1-10.\n4. If ANY < 6: VERDICT: FAIL. If ALL >= 6: VERDICT: PASS.\n"
    echo "$prompt"
}

build_fix_prompt() {
    local qa_report="$1"
    local prompt="You are a senior developer fixing bugs reported by QA.\n\n## RULES\n- Fix ONLY listed bugs.\n- Run php bin/phpunit after fixes.\n- Atomic commits.\n\n## PROJECT CONTEXT\n$(cat "$PROJECT_CONTEXT")\n"
    if [[ -f "$ARCHITECTURE" ]]; then
        prompt+="\n## ARCHITECTURE\n$(cat "$ARCHITECTURE")\n"
    fi
    prompt+="\n## ORIGINAL TASK\n$(cat "$STORY_FILE")\n\n## QA REPORT\n$(cat "$qa_report")\n\n## TASK\nFix each bug, write reproducing test, commit atomically.\n"
    echo "$prompt"
}

for iteration in $(seq 1 "$MAX_ITERATIONS"); do
    echo ">>> Iteration $iteration: QA Evaluation"
    qa_report="$LOG_DIR/qa-report-iteration-$iteration.log"
    previous_report=""
    [[ $iteration -gt 1 ]] && previous_report="$LOG_DIR/qa-report-iteration-$((iteration - 1)).log"
    qa_prompt="$(build_qa_prompt "$iteration" "$previous_report")"
    claude -p "$qa_prompt" --allowedTools "Read,Bash,Glob,Grep" > "$qa_report" 2>&1
    echo "QA report: $qa_report"
    if grep -q "VERDICT: PASS" "$qa_report"; then
        echo ""
        echo "=== PASS at iteration $iteration ==="
        exit 0
    fi
    echo "VERDICT: FAIL"
    if [[ $iteration -eq $MAX_ITERATIONS ]]; then
        echo ""
        echo "=== FAIL after $MAX_ITERATIONS iterations ==="
        exit 1
    fi
    echo ">>> Iteration $iteration: Fixing bugs"
    fix_prompt="$(build_fix_prompt "$qa_report")"
    claude -p "$fix_prompt" --allowedTools "Read,Write,Edit,Bash,Glob,Grep" > "$LOG_DIR/fix-iteration-$iteration.log" 2>&1
    echo "Fix log: $LOG_DIR/fix-iteration-$iteration.log"
    echo ""
done
