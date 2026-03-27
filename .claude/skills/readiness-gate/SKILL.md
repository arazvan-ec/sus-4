# Readiness Gate

## Purpose
Pre-implementation check that verifies specs are complete enough to start coding.

## When to Apply
Before running fresh-exec.sh, qa-loop.sh, or any implementation step.
Works with both BMAD stories and OpenSpec change specs.

## Process

### 1. Detect Spec Type
- If path contains `_bmad-output/` -> BMAD story
- If path contains `openspec/changes/` -> OpenSpec change
- Otherwise -> generic task file

### 2. Check Completeness

**For BMAD stories, verify:**
- Has acceptance criteria (at least 3)
- Has clear scope
- References architecture decisions if relevant
- Dependencies listed and resolved
- Estimation exists (XS/S/M/L/XL)

**For OpenSpec changes, verify:**
- Has proposal.md with clear rationale
- Has spec files with delta markers
- Has design.md with implementation approach
- Has tasks.md with concrete checklist

### 3. Report
**PASS** — All checks green. Safe to implement.
**CONCERNS** — Some checks yellow. List what's missing.
**FAIL** — Critical gaps. Recommend going back to planning.
