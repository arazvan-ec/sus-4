# Story 1.1: Scaffolding del proyecto y modelo de dominio

Status: ready-for-dev

## Story

As a desarrollador,
I want la estructura base del proyecto con entidades de dominio y persistencia configurada,
so that pueda construir features sobre una base solida con las convenciones definidas.

## Acceptance Criteria

1. Existen las capas `src/Domain`, `src/Application`, `src/Infrastructure` con subdirectorios
2. La entidad `Subscription` tiene campos: `id` (UUID), `user_id`, `email`, `entity_type`, `entity_id`, `status`, `created_at`, `updated_at`
3. La entidad `Campaign` tiene campos: `id` (UUID), `type`, `status`, `scheduled_at`, `audience_criteria`, `editorial_id`, `created_at`, `updated_at`
4. `EntityType` es un backed enum PHP 8.4 con valores: `journalist`, `tag`, `section`
5. `CampaignStatus` es un backed enum PHP 8.4 con valores: `pending`, `processing`, `sent`, `failed`
6. El mapeo Doctrine usa XML en `config/doctrine/`
7. Existe migracion para crear tablas `subscriptions` y `campaigns`
8. `subscriptions` tiene indice unico en `(user_id, entity_type, entity_id)`
9. Domain no importa nada de Infrastructure ni de Symfony (NFR-12)
10. Interfaces de repositorio definidas en Domain
11. Implementaciones Doctrine de repositorios en Infrastructure
12. Tests unitarios para entidades de dominio y enums

## Tasks / Subtasks

- [ ] Task 1: Inicializar proyecto Symfony 7.2 (AC: 1)
  - [ ] 1.1 `composer create-project symfony/skeleton:"7.2.*"` o configurar `composer.json` manualmente
  - [ ] 1.2 Instalar dependencias: `doctrine/orm`, `doctrine/doctrine-bundle`, `doctrine/doctrine-migrations-bundle`, `symfony/messenger`, `symfony/amqp-messenger`
  - [ ] 1.3 Crear estructura de directorios: `src/Domain/`, `src/Application/Handler/`, `src/Application/Message/`, `src/Application/Service/`, `src/Infrastructure/Controller/`, `src/Infrastructure/Repository/`, `src/Infrastructure/Client/`, `src/Infrastructure/Command/`, `src/Infrastructure/Rendering/`, `src/Infrastructure/Security/`
  - [ ] 1.4 Configurar autoload PSR-4 en composer.json

- [ ] Task 2: Crear enums de dominio (AC: 4, 5, 9)
  - [ ] 2.1 `src/Domain/EntityType.php` — backed string enum
  - [ ] 2.2 `src/Domain/CampaignStatus.php` — backed string enum

- [ ] Task 3: Crear entidad Subscription (AC: 2, 9, 10)
  - [ ] 3.1 `src/Domain/Subscription.php` — clase final, sin imports de Symfony/Doctrine
  - [ ] 3.2 `src/Domain/SubscriptionRepositoryInterface.php`
  - [ ] 3.3 Constructor con validacion de dominio

- [ ] Task 4: Crear entidad Campaign (AC: 3, 9, 10)
  - [ ] 4.1 `src/Domain/Campaign.php` — clase final, sin imports de Symfony/Doctrine
  - [ ] 4.2 `src/Domain/CampaignRepositoryInterface.php`

- [ ] Task 5: Crear excepciones y value objects de dominio (AC: 9)
  - [ ] 5.1 `src/Domain/PermanentErrorException.php`
  - [ ] 5.2 `src/Domain/TransientErrorException.php`
  - [ ] 5.3 `src/Domain/ErrorType.php`

- [ ] Task 6: Mapeo Doctrine XML (AC: 6, 8)
  - [ ] 6.1 `config/doctrine/Subscription.orm.xml` con indice unico compuesto
  - [ ] 6.2 `config/doctrine/Campaign.orm.xml` con JSONB para audience_criteria
  - [ ] 6.3 Configurar `config/packages/doctrine.yaml` con `type: xml` y `dir: '%kernel.project_dir%/config/doctrine'`

- [ ] Task 7: Implementar repositorios Doctrine (AC: 11)
  - [ ] 7.1 `src/Infrastructure/Repository/DoctrineSubscriptionRepository.php` implementando `SubscriptionRepositoryInterface`
  - [ ] 7.2 `src/Infrastructure/Repository/DoctrineCampaignRepository.php` implementando `CampaignRepositoryInterface`

- [ ] Task 8: Crear migracion de base de datos (AC: 7, 8)
  - [ ] 8.1 Ejecutar `bin/console doctrine:migrations:diff` o crear migracion manual
  - [ ] 8.2 Verificar que incluye indice unico en subscriptions `(user_id, entity_type, entity_id)`
  - [ ] 8.3 Verificar que `audience_criteria` usa tipo JSONB

- [ ] Task 9: Configuracion base del proyecto
  - [ ] 9.1 `config/packages/doctrine.yaml` — conexion PostgreSQL, mapeo XML
  - [ ] 9.2 `config/packages/framework.yaml` — configuracion base
  - [ ] 9.3 `config/services.yaml` — autowiring, binding de interfaces a implementaciones
  - [ ] 9.4 `.env` — DATABASE_URL con postgres
  - [ ] 9.5 `phpunit.dist.xml` — configuracion de tests

- [ ] Task 10: Tests unitarios (AC: 12)
  - [ ] 10.1 `tests/Unit/Domain/EntityTypeTest.php`
  - [ ] 10.2 `tests/Unit/Domain/CampaignStatusTest.php`
  - [ ] 10.3 `tests/Unit/Domain/SubscriptionTest.php`
  - [ ] 10.4 `tests/Unit/Domain/CampaignTest.php`

## Dev Notes

### Arquitectura — REGLAS CRITICAS

- **Domain PURO**: `src/Domain/` no puede tener ningun `use` de `Symfony\*`, `Doctrine\*` ni ninguna dependencia externa. Solo PHP nativo.
- **Clases final**: Todas las entidades y servicios son `final class`. Mocking se hace via interfaces.
- **Type declarations**: Parametros tipados y return types en todo.
- **PSR-12**: Coding style estricto.

### Entidades de dominio — Diseno

**Subscription.php:**
```php
final class Subscription
{
    private string $id;
    private string $userId;
    private string $email;
    private EntityType $entityType;
    private string $entityId;
    private string $status; // 'active' o 'inactive'
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;
}
```
- Constructor recibe todos los campos obligatorios
- Metodo `deactivate()` cambia status a 'inactive' y actualiza updatedAt
- Metodo `reactivate()` cambia status a 'active' y actualiza updatedAt
- Metodo `isActive(): bool`
- NO usar setters. Estado se muta via metodos de dominio con nombre semantico.

**Campaign.php:**
```php
final class Campaign
{
    private string $id;
    private string $type;
    private CampaignStatus $status;
    private \DateTimeImmutable $scheduledAt;
    private array $audienceCriteria; // sera JSONB en PG
    private string $editorialId;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;
}
```
- Constructor con status por defecto `CampaignStatus::Pending`
- Metodo `markAsProcessing()`, `markAsSent()`, `markAsFailed()`
- Metodo `isReadyToProcess(): bool` — verifica `status === pending && scheduledAt <= now`

### Enums — PHP 8.4 backed enums

```php
enum EntityType: string
{
    case Journalist = 'journalist';
    case Tag = 'tag';
    case Section = 'section';
}

enum CampaignStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Sent = 'sent';
    case Failed = 'failed';
}
```

### Interfaces de repositorio

```php
interface SubscriptionRepositoryInterface
{
    public function findByUserAndEntity(string $userId, EntityType $entityType, string $entityId): ?Subscription;
    public function save(Subscription $subscription): void;
}

interface CampaignRepositoryInterface
{
    public function save(Campaign $campaign): void;
    public function findPendingReadyToProcess(\DateTimeImmutable $now): array;
}
```

### Mapeo XML Doctrine

**Subscription.orm.xml** — Puntos criticos:
- `entity name="App\Domain\Subscription" table="subscriptions"`
- `id` tipo `guid` con generador UUID
- `entity_type` como `string` (Doctrine mapea el valor del enum, no el nombre)
- `status` como `string` (no enum en DB, para simplicidad de queries)
- `audience_criteria` en Campaign como tipo `json` (mapea a JSONB en PostgreSQL)
- Indice unico: `<unique-constraint columns="user_id,entity_type,entity_id" name="uniq_subscription"/>`

### Configuracion Doctrine

```yaml
# config/packages/doctrine.yaml
doctrine:
  dbal:
    url: '%env(resolve:DATABASE_URL)%'
  orm:
    auto_generate_proxy_classes: true
    naming_strategy: doctrine.orm.naming_strategy.underscore_number_aware
    auto_mapping: false
    mappings:
      App:
        type: xml
        dir: '%kernel.project_dir%/config/doctrine'
        prefix: 'App\Domain'
        alias: App
```

**CRITICO**: El prefix es `App\Domain` (no `App\Entity`) porque las entidades estan en Domain.

### Project Structure Notes

Estructura final esperada tras completar esta story:
```
src/
├── Kernel.php
├── Domain/
│   ├── Subscription.php
│   ├── Campaign.php
│   ├── EntityType.php
│   ├── CampaignStatus.php
│   ├── SubscriptionRepositoryInterface.php
│   ├── CampaignRepositoryInterface.php
│   ├── ErrorType.php
│   ├── PermanentErrorException.php
│   └── TransientErrorException.php
├── Application/
│   ├── Handler/
│   ├── Message/
│   └── Service/
└── Infrastructure/
    ├── Controller/
    ├── Repository/
    │   ├── DoctrineSubscriptionRepository.php
    │   └── DoctrineCampaignRepository.php
    ├── Client/
    ├── Command/
    ├── Rendering/
    └── Security/
```

### Testing

- Tests unitarios en `tests/Unit/Domain/` para entidades y enums
- Verificar que las entidades se pueden instanciar sin framework
- Verificar transiciones de estado (active/inactive, pending/processing/sent/failed)
- Verificar validacion de EntityType (solo valores permitidos)
- NO necesita tests de integracion en esta story (se haran cuando haya DB real)

### References

- [Source: _bmad-output/planning-artifacts/architecture.md#5. Estructura del Proyecto]
- [Source: _bmad-output/planning-artifacts/architecture.md#3. Decisiones Arquitectonicas — AD-01, AD-02]
- [Source: _bmad-output/planning-artifacts/architecture.md#4. Patrones de Implementacion]
- [Source: _bmad-output/planning-artifacts/prd.md#5. Requisitos Funcionales — FR-SUB-01..07]
- [Source: _bmad-output/planning-artifacts/prd.md#6. Requisitos No Funcionales — NFR-11, NFR-12]
- [Source: _bmad-output/planning-artifacts/epics-and-stories.md#Story 1.1]
- [Doctrine ORM 3.6 XML Mapping](https://www.doctrine-project.org/projects/doctrine-orm/en/3.6/reference/xml-mapping.html)

## Dev Agent Record

### Agent Model Used

(pendiente — sera completado por el agente de desarrollo)

### Completion Notes List

### File List
