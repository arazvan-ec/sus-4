# sus-4

## Workflow

Este proyecto usa un workflow combinado BMAD + ECC + OpenSpec.
La guia completa esta en `docs/workflow-guide.md`.

### Flujos disponibles

- **Proyecto nuevo / feature compleja:** BMAD fases 1-3 -> ECC ejecucion
- **Feature en servicio existente:** OpenSpec -> ECC ejecucion
- **Bug / cambio menor:** bmad-quick-dev o Claude Code directo

### Referencia rapida

| Quiero... | Comando |
|-----------|----------|
| Planificar proyecto nuevo | bmad-analyst -> bmad-pm -> bmad-architect |
| Especificar feature brownfield | /opsx:propose "descripcion" |
| Investigar antes de planificar | deep-research "tema" |
| Verificar specs antes de codear | readiness-gate |
| Implementar con contexto limpio | ./.claude/scripts/fresh-exec.sh task.md |
| Implementar + QA automatico | ./.claude/scripts/qa-loop.sh task.md 3 |
| Implementar en paralelo | ./.claude/scripts/parallel-tasks.sh a.md b.md |
| Code review adversarial | bmad-code-review |
| Bug fix rapido | bmad-quick-dev |
| CI fallo, diagnosticar | ./.claude/scripts/ci-reactor.sh log.txt |

## Convenciones

### Arquitectura
- Tres capas: Domain / Application / Infrastructure
- Domain no importa nada de Infrastructure ni de Symfony
- REST APIs con RFC 7807 para errores
- Eventos async via RabbitMQ + Symfony Messenger

### Codigo
- PHP 8.4+, Symfony 7.2
- Clases final por defecto
- Type declarations en parametros y returns
- PSR-12 coding style

### Tests
- PHPUnit para unit y functional
- Interfaces de dominio para mocking (clases final no se pueden mockear)
- TDD: tests primero

### Git
- Commits atomicos: un cambio logico por commit
- Formato: `type: descripcion` (init, feat, fix, refactor, test, docs, setup, ext, chore)
