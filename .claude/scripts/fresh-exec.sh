#!/usr/bin/env bash
# fresh-exec.sh — Execute a task with a fresh context sub-agent
#
# Works with BMAD stories, OpenSpec tasks, or any markdown task file.
# Auto-detects and injects relevant Symfony skills by keywords.
#
# Usage:
#   ./.claude/scripts/fresh-exec.sh <task-file> [extra-context-files...]

set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
PROJECT_CONTEXT="$PROJECT_ROOT/_bmad-output/project-context.md"
ARCHITECTURE="$PROJECT_ROOT/_bmad-output/planning-artifacts/architecture.md"
SKILLS_DIR="$PROJECT_ROOT/.claude/skills"
TIMEOUT="${FRESH_EXEC_TIMEOUT:-600}"

if [[ $# -lt 1 ]]; then
    echo "Usage: $0 <task-file> [extra-context-files...]"
    exit 1
fi

TASK_FILE="$1"
shift

if [[ ! -f "$TASK_FILE" ]]; then
    echo "ERROR: Task file not found: $TASK_FILE"
    exit 1
fi

detect_skills() {
    local task_content
    task_content="$(cat "$TASK_FILE")"
    local detected=()
    if echo "$task_content" | grep -qiE "controller|endpoint|API|route|request|response"; then
        detected+=("symfony-api")
    fi
    if echo "$task_content" | grep -qiE "consumer|Messenger|event|handler|message|RabbitMQ|queue"; then
        detected+=("symfony-messenger")
    fi
    if echo "$task_content" | grep -qiE "entity|repository|migration|Doctrine|ORM|query|table"; then
        detected+=("symfony-doctrine")
    fi
    if echo "$task_content" | grep -qiE "cache|CDN|Vary|Transparent Edge|purge|ttl"; then
        detected+=("cdn-caching")
    fi
    echo "${detected[@]}"
}

PROMPT="You are a senior developer implementing a task for a Symfony project.

## CRITICAL RULES
- Implement ONLY what the task asks. Nothing more.
- Write tests FIRST (TDD). Use PHPUnit.
- Make atomic commits: one logical change per commit.
- Follow the coding conventions in the project context.
- Do NOT modify files unrelated to the task.
- Use domain interfaces for dependencies (final classes can't be mocked).
"

if [[ -f "$PROJECT_CONTEXT" ]]; then
    PROMPT+="\n## PROJECT CONTEXT\n<project-context>\n$(cat "$PROJECT_CONTEXT")\n</project-context>\n"
fi

if [[ -f "$ARCHITECTURE" ]]; then
    PROMPT+="\n## ARCHITECTURE\n<architecture>\n$(cat "$ARCHITECTURE")\n</architecture>\n"
fi

DETECTED_SKILLS=($(detect_skills))
if [[ ${#DETECTED_SKILLS[@]} -gt 0 ]]; then
    echo "Auto-detected skills: ${DETECTED_SKILLS[*]}"
    for skill in "${DETECTED_SKILLS[@]}"; do
        skill_file="$SKILLS_DIR/$skill/SKILL.md"
        if [[ -f "$skill_file" ]]; then
            PROMPT+="\n## SKILL: $skill\n<skill>\n$(cat "$skill_file")\n</skill>\n"
        fi
    done
fi

for extra_file in "$@"; do
    if [[ -f "$extra_file" ]]; then
        PROMPT+="\n## ADDITIONAL CONTEXT: $(basename "$extra_file")\n<additional-context>\n$(cat "$extra_file")\n</additional-context>\n"
    else
        echo "WARNING: Extra context file not found, skipping: $extra_file"
    fi
done

PROMPT+="\n## TASK TO IMPLEMENT\n<task>\n$(cat "$TASK_FILE")\n</task>\n\n## YOUR TASK\n1. Read and understand the task and its acceptance criteria.\n2. Plan the implementation.\n3. Write the tests first (PHPUnit).\n4. Implement the code to make the tests pass.\n5. Run php bin/phpunit to verify all tests pass.\n6. Make atomic commits for each logical change.\n7. Summarize what you did.\n"

echo "=== Fresh Exec ==="
echo "Task: $TASK_FILE"
[[ -f "$PROJECT_CONTEXT" ]] && echo "Context: $PROJECT_CONTEXT"
[[ -f "$ARCHITECTURE" ]] && echo "Architecture: $ARCHITECTURE"
echo "Skills: ${DETECTED_SKILLS[*]:-none}"
echo "==================="
echo ""

claude -p "$PROMPT" --allowedTools "Read,Write,Edit,Bash,Glob,Grep"
