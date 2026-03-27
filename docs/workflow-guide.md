# Workflow Completo: BMAD + ECC + OpenSpec

## Que es esto

Un sistema unificado que combina lo mejor de cada herramienta del ecosistema.

    Proyecto nuevo  ->  BMAD fases 1-3  ->  ECC ejecucion + QA + review
    Feature brownfield  ->  OpenSpec  ->  ECC ejecucion + QA + review
    Bug / cambio menor  ->  ECC directo (o bmad-quick-dev)

## Como usar esta guia

Esta guia se carga automaticamente via CLAUDE.md en la raiz del proyecto.
Los skills de BMAD tienen flujo interactivo con menus — dejalos hacer preguntas.

Requisitos:
1. BMAD instalado (npx bmad-method install)
2. Scripts ejecutables (chmod +x .claude/scripts/*.sh)
3. project-context.md generado (bmad-generate-project-context)

ECC y OpenSpec son opcionales.

---

## Flujo A: Proyecto nuevo (BMAD completo)

### 1. Analisis
    bmad-analyst
Escribir BP (Brainstorm), luego CB (Create Brief).

### 2. Planning
    bmad-pm
Escribir CP (Create PRD).

### 3. Solutioning
    bmad-architect
Escribir CA (Create Architecture).
Gate check: bmad-check-implementation-readiness

### 4. Crear stories
    bmad-pm -> EP (Create Epics)
    bmad-sm -> SP (Sprint Planning), CS (Create Story)

### 5. Implementar
    ./.claude/scripts/fresh-exec.sh story.md
    ./.claude/scripts/qa-loop.sh story.md 3
    ./.claude/scripts/parallel-tasks.sh story-a.md story-b.md

### 6. Code review
    bmad-code-review
    bmad-review-edge-case-hunter

### 7. Security (ECC)
    npx ecc-agentshield scan

---

## Flujo B: Feature en proyecto existente (OpenSpec)

### 1. Proponer
    /opsx:propose "descripcion del cambio"

### 2. Gate check
    readiness-gate

### 3. Implementar
    ./.claude/scripts/qa-loop.sh openspec/changes/[feature]/tasks.md 3

### 4. Verificar
    /opsx:verify
    /opsx:archive

### 5. Review + Security
    bmad-code-review
    npx ecc-agentshield scan

---

## Flujo C: Bugs y cambios menores
    bmad-quick-dev

---

## Post-merge: CI/CD reactivo
    ./.claude/scripts/ci-reactor.sh path/to/ci-log.txt

---

## Cheatsheet

| Quiero... | Comando |
|-----------|----------|
| Planificar proyecto nuevo | bmad-analyst -> bmad-pm -> bmad-architect |
| Feature brownfield | /opsx:propose "descripcion" |
| Investigar | deep-research "tema" |
| Gate check | readiness-gate |
| Implementar limpio | ./.claude/scripts/fresh-exec.sh task.md |
| Implementar + QA | ./.claude/scripts/qa-loop.sh task.md 3 |
| Paralelo | ./.claude/scripts/parallel-tasks.sh a.md b.md |
| Code review | bmad-code-review |
| Bug fix | bmad-quick-dev |
| CI fallo | ./.claude/scripts/ci-reactor.sh log.txt |

---

## Estructura de archivos

    .claude/
    +-- skills/          # bmad-* (43) + openspec-* (4) + custom (7)
    +-- agents/          # ECC (tras instalacion)
    +-- scripts/         # fresh-exec, worktree, parallel, qa-loop, ci-reactor
    _bmad-output/        # Artefactos BMAD
    openspec/            # OpenSpec (tras instalacion)

---

## Cuando usar que flujo

| Situacion | Flujo | Coste |
|-----------|-------|-------|
| Microservicio nuevo | A (BMAD) | $20-40 |
| Feature compleja | A (BMAD) | $15-30 |
| Feature existente | B (OpenSpec) | $10-25 |
| Feature pequena | C (quick-dev) | $2-5 |
| Bug fix | C (quick-dev) | $1-3 |
| Config/typo | Claude directo | $0.10 |

---

## Notas

- Los skills Symfony necesitan validacion A/B antes de confiar en ellos
- fresh-exec.sh auto-detecta skills por keywords (API, Messenger, Doctrine, CDN)
- Si QA loop no converge en 3, hay un problema de diseno
- MAX_PARALLEL=2 si hay rate limiting
