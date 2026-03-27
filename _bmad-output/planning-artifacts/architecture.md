---
stepsCompleted: [step-01-init, step-02-context, step-03-starter, step-04-decisions, step-05-patterns, step-06-structure, step-07-validation, step-08-complete]
inputDocuments: [product-brief-enbandeja-service.md, project-context.md, prd.md]
workflowType: 'architecture'
project_name: 'sus-4'
date: '2026-03-27'
---

# Documento de Arquitectura: enBandeja-service

## 1. Contexto del Proyecto

### Resumen

Microservicio que gestiona suscripciones de usuarios a entidades editoriales (periodistas, tags, secciones) y orquesta notificaciones por email al publicarse contenido relevante.

### Drivers arquitectonicos

| Driver | Descripcion |
|--------|-------------|
| Latencia baja en API REST | p95 <= 100 ms en consultas, <= 200 ms en escrituras |
| Desacoplamiento de envio | enBandeja orquesta campanas; notifier-service envia emails |
| Compatibilidad con CDN | Paginas cacheadas en Transparent Edge; personalizacion solo en cliente |
| Fiabilidad en eventos | Cero mensajes perdidos en RabbitMQ; ACK tras procesamiento exitoso |
| Soft delete | Suscripciones nunca se eliminan fisicamente |

### Escala estimada (MVP)

- Hasta 10 000 suscriptores por campana
- Decenas de editoriales publicados por dia
- Miles de consultas de estado de suscripcion por minuto (CDN no cachea estas llamadas)

---

## 2. Stack Tecnologico

| Componente | Tecnologia | Version | Justificacion |
|------------|-----------|---------|---------------|
| Lenguaje | PHP | 8.4+ | Consistencia con ecosistema existente |
| Framework | Symfony | 7.2 | Messenger integrado, Doctrine, ecosistema maduro |
| ORM | Doctrine | 3.x | Mapeo XML, migraciones, unit of work |
| Base de datos | PostgreSQL | 16+ | JSONB para audience_criteria, indices parciales |
| Broker | RabbitMQ | 3.13+ | Via Symfony Messenger, dead letter queues nativas |
| HTTP Client | Symfony HttpClient | 7.2 | Para integraciones con servicios externos |
| Testing | PHPUnit | 11+ | Unit + functional tests |

---

## 3. Decisiones Arquitectonicas

### AD-01: Tres capas (Domain / Application / Infrastructure)

**Decision**: Arquitectura hexagonal simplificada con tres capas.

- **Domain**: Entidades, value objects, interfaces de repositorio y servicios. Sin dependencias externas.
- **Application**: Casos de uso (handlers, services). Depende solo de Domain.
- **Infrastructure**: Implementaciones concretas (Doctrine, HTTP clients, Messenger). Depende de Domain y Application.

**Razon**: Domain puro permite testing sin framework. Las interfaces en Domain permiten mockear clases final.

### AD-02: Soft delete en suscripciones

**Decision**: El campo `status` (active/inactive) controla el estado logico. DELETE no elimina registros.

**Razon**: Permite reactivar suscripciones sin perder historial. Simplifica reconciliacion con Mailchimp.

**Implementacion**: Indice unico compuesto en `(user_id, entity_type, entity_id)`. No se usa filtro de Doctrine; las queries filtran explicitamente por status.

### AD-03: JWT solo validacion, no emision

**Decision**: enBandeja valida JWT recibidos en cabecera Authorization. No emite tokens.

**Razon**: La autenticacion la gestiona un servicio externo. enBandeja extrae `user_id` y `email` de los claims.

**Implementacion**: Middleware de Symfony Security con custom authenticator que valida firma y claims.

### AD-04: Sincronizacion asincrona con Mailchimp

**Decision**: Las operaciones de audiencia (add/remove) en Mailchimp Marketing API se ejecutan asincronamente via Symfony Messenger.

**Razon**: No bloquear la respuesta al usuario. Los fallos en Mailchimp no afectan la suscripcion local.

**Implementacion**: Mensaje `SyncAudience` despachado tras crear/desactivar suscripcion. Handler con retry automatico (3 intentos, backoff exponencial).

### AD-05: Campaign como entidad de orquestacion

**Decision**: Las campanas son registros que modelan el ciclo completo: creacion -> scheduling -> resolucion de audiencia -> despacho.

**Estados**: `pending` -> `processing` -> `sent` | `failed`

**Razon**: Desacopla la recepcion del evento editorial de la resolucion y envio. Permite scheduling y reintentos.

### AD-06: Comunicacion con notifier-service via RabbitMQ

**Decision**: El despacho de campanas resueltas se envia como mensaje a notifier-service via RabbitMQ, no por HTTP.

**Razon**: Desacoplamiento total. notifier-service consume a su ritmo. Tolerancia a fallos del servicio de envio.

### AD-07: API REST con RFC 7807 para errores

**Decision**: Todas las respuestas de error siguen el formato RFC 7807 (Problem Details).

**Formato**:
```json
{
  "type": "https://enbandeja.example.com/errors/subscription-not-found",
  "title": "Subscription Not Found",
  "status": 404,
  "detail": "No subscription found for journalist/123"
}
```

---

## 4. Patrones de Implementacion

### 4.1 Naming conventions

| Elemento | Patron | Ejemplo |
|----------|--------|---------|
| Entidad de dominio | PascalCase, sustantivo | `Subscription`, `Campaign` |
| Value object | PascalCase, descriptivo | `EntityType`, `CampaignStatus` |
| Interface de dominio | PascalCase + Interface | `SubscriptionRepositoryInterface` |
| Handler | PascalCase + Handler | `EditorialPublishedHandler` |
| Message | PascalCase, accion | `SendCampaign`, `SyncAudience` |
| Command | PascalCase + Command | `ReconcileCampaignsCommand` |
| Controller | PascalCase + Controller | `SubscriptionController` |
| Test | PascalCase + Test | `SubscriptionTest`, `CampaignProcessorTest` |

### 4.2 Patron de mensajes

```
Mensaje (Application/Message) -> Handler (Application/Handler) -> Servicios de dominio
```

- Un mensaje = un handler. Sin logica de negocio en el handler, solo orquestacion.
- Los handlers llaman a servicios de Application o Domain.
- Messenger configura transport, retry y dead letter.

### 4.3 Error handling

| Capa | Estrategia |
|------|-----------|
| Domain | Excepciones de dominio (`SubscriptionNotFoundException`, `InvalidEntityTypeException`) |
| Application | Los handlers capturan excepciones de infraestructura y las traducen |
| Infrastructure | RFC 7807 error listener transforma excepciones en respuestas HTTP |
| Messenger | Retry automatico (3 intentos) + dead letter queue para mensajes fallidos |

### 4.4 Testing

| Tipo | Alcance | Herramienta |
|------|---------|-------------|
| Unit | Domain + Application | PHPUnit, mocks via interfaces |
| Integration | Infrastructure (repositories, clients) | PHPUnit + test database |
| Functional | API endpoints | PHPUnit + Symfony WebTestCase |

---

## 5. Estructura del Proyecto

```
sus-4/
├── bin/
│   └── console
├── config/
│   ├── bundles.php
│   ├── packages/
│   │   ├── doctrine.yaml
│   │   ├── framework.yaml
│   │   ├── messenger.yaml
│   │   └── security.yaml
│   ├── routes.yaml
│   ├── services.yaml
│   └── doctrine/
│       ├── Subscription.orm.xml
│       └── Campaign.orm.xml
├── migrations/
├── public/
│   └── index.php
├── src/
│   ├── Kernel.php
│   ├── Domain/
│   │   ├── Subscription.php
│   │   ├── Campaign.php
│   │   ├── EntityType.php                    # enum: journalist, tag, section
│   │   ├── CampaignStatus.php                # enum: pending, processing, sent, failed
│   │   ├── SubscriptionRepositoryInterface.php
│   │   ├── CampaignRepositoryInterface.php
│   │   ├── MailchimpClientInterface.php
│   │   ├── EditorialServiceInterface.php
│   │   ├── JournalistServiceInterface.php
│   │   ├── EditorialData.php                 # value object
│   │   ├── JournalistData.php                # value object
│   │   ├── ErrorType.php
│   │   ├── PermanentErrorException.php
│   │   └── TransientErrorException.php
│   ├── Application/
│   │   ├── Handler/
│   │   │   ├── EditorialPublishedHandler.php
│   │   │   └── SendCampaignHandler.php
│   │   ├── Message/
│   │   │   ├── EditorialPublished.php
│   │   │   ├── SendCampaign.php
│   │   │   └── SyncAudience.php
│   │   └── Service/
│   │       ├── CampaignProcessor.php
│   │       ├── CampaignProcessorInterface.php
│   │       └── CampaignReconciler.php
│   └── Infrastructure/
│       ├── Client/
│       │   ├── MailchimpClient.php
│       │   ├── JournalistServiceClient.php
│       │   ├── EditorialServiceClient.php
│       │   ├── TagServiceClient.php
│       │   └── SectionServiceClient.php
│       ├── Controller/
│       │   └── SubscriptionController.php
│       ├── Repository/
│       │   ├── DoctrineSubscriptionRepository.php
│       │   └── DoctrineCampaignRepository.php
│       ├── Rendering/
│       │   └── TwigEmailRenderer.php
│       ├── Command/
│       │   └── ReconcileCampaignsCommand.php
│       └── Security/
│           └── JwtAuthenticator.php
├── templates/
│   └── email/
├── tests/
│   ├── Unit/
│   │   ├── Domain/
│   │   └── Application/
│   └── Functional/
│       └── Infrastructure/
├── composer.json
├── phpunit.dist.xml
├── .env
├── docker/
├── CLAUDE.md
├── _bmad/
├── _bmad-output/
└── docs/
```

### Mapeo de requisitos a estructura

| Requisito | Componente |
|-----------|-----------|
| FR-SUB-01..07 | SubscriptionController, Subscription, SubscriptionRepositoryInterface |
| FR-AUD-01..05 | SyncAudience message, MailchimpClientInterface, MailchimpClient |
| FR-EDT-01..04 | EditorialPublishedHandler, EditorialServiceInterface, JournalistServiceInterface |
| FR-CMP-01..06 | Campaign, CampaignProcessor, SendCampaignHandler, CampaignRepositoryInterface |
| NFR-09 (JWT) | JwtAuthenticator |
| NFR-14 (health) | HealthController (por agregar) |

---

## 6. Integraciones Externas

### INT-01: Mailchimp Marketing API (audiencias)

- **Direccion**: Saliente, asincrono
- **Patron**: Mensaje `SyncAudience` -> Handler -> `MailchimpClient`
- **Retry**: 3 intentos con backoff exponencial (1s, 4s, 16s)
- **Fallo**: Dead letter queue. No afecta suscripcion local.
- **Operaciones**: Add member to list, Remove member from list

### INT-02: notifier-service (envio de emails)

- **Direccion**: Saliente, asincrono via RabbitMQ
- **Patron**: `SendCampaignHandler` -> publica mensaje con destinatarios + contenido
- **Contrato**: Mensaje con `recipients[]`, `editorial_id`, `content`
- **Fallo**: Campana permanece en `pending` para reintento

### INT-03: Legacy/Jarvis CMS (eventos editoriales)

- **Direccion**: Entrante, asincrono via RabbitMQ
- **Evento**: `editorial.published`
- **Payload esperado**: `{editorial_id, journalist_id, tag_ids[], section_ids[], title, url}`
- **Consumer**: `EditorialPublishedHandler`

### INT-04/05/06: journalist-svc, tag-svc, section-svc

- **Direccion**: Saliente, sincrono HTTP
- **Proposito**: Verificar flag de habilitacion para notificaciones
- **Patron**: `GET /api/{entity}/{id}/notification-flag` -> `{enabled: true/false}`
- **Timeout**: 2s por llamada. Si fallo -> asume flag deshabilitado (fail-safe).

---

## 7. Validacion

### Cobertura de requisitos

| Grupo FR | Componente(s) que cubren | Estado |
|----------|-------------------------|--------|
| FR-SUB (7) | SubscriptionController + Subscription + Repository | Cubierto |
| FR-AUD (5) | SyncAudience + MailchimpClient | Cubierto |
| FR-EDT (4) | EditorialPublishedHandler + Service clients | Cubierto |
| FR-CMP (6) | Campaign + CampaignProcessor + SendCampaignHandler | Cubierto |

### Coherencia

| Check | Resultado |
|-------|-----------|
| Domain sin imports de Infrastructure | OK — Solo interfaces en Domain |
| Clases final por defecto | OK — Mocking via interfaces |
| Soft delete consistente | OK — status active/inactive, indice unico compuesto |
| Async no bloquea sync | OK — Mailchimp sync via Messenger, separate transport |
| JWT solo validacion | OK — Custom authenticator, no emision |
| RFC 7807 en errores | OK — Error listener global |
| Dead letter para fallos | OK — Messenger retry + DLQ configurado |
