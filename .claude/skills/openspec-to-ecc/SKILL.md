# OpenSpec to ECC Bridge

## Purpose
Converts OpenSpec change artifacts into a format that ECC execution agents can consume.

## When to Apply
After running `/opsx:propose` and before implementing with fresh-exec.sh or ECC agents.

## Process

### 1. Read OpenSpec Change
Load the change directory from `openspec/changes/[feature]/`:
- `proposal.md` — why the change
- `specs/*.md` — what changes (with ADDED/MODIFIED/REMOVED markers)
- `design.md` — how to implement
- `tasks.md` — checklist of implementation tasks

### 2. Generate Task File
Create a consolidated task file that fresh-exec.sh can consume.

### 3. Inject Relevant Skills
Based on keywords in the specs, the bridge identifies which Symfony skills
to recommend to fresh-exec.sh.

### Output
A single markdown file at `openspec/changes/[feature]/ecc-task.md` ready
for fresh-exec.sh or qa-loop.sh.
