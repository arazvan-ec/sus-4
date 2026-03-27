#!/usr/bin/env bash
# setup-workflow.sh — Replica el workflow BMAD+ECC+OpenSpec en otro repo
#
# Uso:
#   ./.claude/scripts/setup-workflow.sh /path/al/otro/repo

set -euo pipefail

if [[ $# -lt 1 ]]; then
    echo "Uso: $0 /path/al/otro/repo"
    exit 1
fi

TARGET="$(cd "$1" && pwd)"
SOURCE="$(cd "$(dirname "$0")/../.." && pwd)"

echo "=== Setup Workflow ==="
echo "Source: $SOURCE"
echo "Target: $TARGET"
echo "======================"
echo ""

echo ">>> Paso 1/7: Copiando scripts..."
mkdir -p "$TARGET/.claude/scripts"
for script in fresh-exec.sh worktree-exec.sh parallel-tasks.sh qa-loop.sh ci-reactor.sh; do
    [[ -f "$SOURCE/.claude/scripts/$script" ]] && cp "$SOURCE/.claude/scripts/$script" "$TARGET/.claude/scripts/"
done
chmod +x "$TARGET/.claude/scripts/"*.sh 2>/dev/null || true

echo ">>> Paso 2/7: Copiando skills custom..."
for skill in symfony-api symfony-messenger symfony-doctrine cdn-caching openspec-to-ecc readiness-gate deep-research; do
    [[ -d "$SOURCE/.claude/skills/$skill" ]] && mkdir -p "$TARGET/.claude/skills/$skill" && cp "$SOURCE/.claude/skills/$skill/SKILL.md" "$TARGET/.claude/skills/$skill/"
done

echo ">>> Paso 3/7: Copiando workflow guide..."
mkdir -p "$TARGET/docs"
cp "$SOURCE/docs/workflow-guide.md" "$TARGET/docs/"

echo ">>> Paso 4/7: Instalando BMAD Method..."
if command -v npx &> /dev/null; then
    (cd "$TARGET" && npx bmad-method install --directory "$TARGET" --modules bmm --tools claude-code --communication-language Spanish --document-output-language Spanish --yes 2>&1) | tail -5
else
    echo "  WARN: npx no disponible. Instala BMAD manualmente."
fi

echo ">>> Paso 5/7: Instalando OpenSpec..."
if command -v npm &> /dev/null; then
    command -v openspec &> /dev/null || npm install -g @fission-ai/openspec 2>&1 | tail -3
    (cd "$TARGET" && openspec init 2>&1) | tail -3
else
    echo "  WARN: npm no disponible. Instala OpenSpec manualmente."
fi

echo ">>> Paso 6/7: ECC (requiere Claude Code interactivo)..."
echo "  Ejecuta: /plugin marketplace add affaan-m/everything-claude-code"
echo "  Luego: /plugin install everything-claude-code"

echo ">>> Paso 7/7: Preparando directorios..."
mkdir -p "$TARGET/_bmad-output/planning-artifacts" "$TARGET/_bmad-output/implementation-artifacts"

echo ""
echo "=== Setup completo ==="
echo "Pasos manuales: adaptar CLAUDE.md, instalar ECC, generar project-context.md"
