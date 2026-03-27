---
stepsCompleted: [step-01-validate-prerequisites, step-02-design-epics, step-03-create-stories, step-04-final-validation]
inputDocuments: [prd.md, architecture.md, product-brief-enbandeja-service.md]
---

# sus-4 (enBandeja-service) - Epic Breakdown

## Overview

Descomposicion de los requisitos del PRD y decisiones de arquitectura en epics y stories implementables. Cada epic entrega valor autonomo y puede desplegarse independientemente.

## Requirements Inventory

### Functional Requirements

- FR-SUB-01: Consultar si un usuario esta suscrito a una entidad
- FR-SUB-02: Crear una suscripcion asociando usuario a entidad
- FR-SUB-03: Reactivar suscripcion inactiva en vez de crear duplicado
- FR-SUB-04: Desactivar suscripcion (soft delete)
- FR-SUB-05: Autenticacion JWT obligatoria; 401 sin JWT valido
- FR-SUB-06: Tipos soportados: journalist, tag, section; 400 para tipos invalidos
- FR-SUB-07: Respuesta de consulta incluye campo booleano de estado
- FR-AUD-01: Al crear/reactivar suscripcion, anadir usuario a audiencia Mailchimp
- FR-AUD-02: Al desactivar suscripcion, eliminar usuario de audiencia Mailchimp
- FR-AUD-03: Sincronizacion de audiencias no bloquea respuesta al usuario
- FR-AUD-04: Fallos en sync de audiencia no afectan suscripcion local
- FR-AUD-05: Reintentos automaticos en fallos de sync de audiencia
- FR-EDT-01: Consumir eventos editorial.published del broker
- FR-EDT-02: Verificar flags de habilitacion de entidades en servicios externos
- FR-EDT-03: Descartar evento si ninguna entidad tiene seguidores activos
- FR-EDT-04: Crear campana si al menos una entidad tiene seguidores activos
- FR-CMP-01: Campana registra tipo, estado, scheduled_at, audience_criteria, editorial_id
- FR-CMP-02: Procesar campanas pendientes cuyo scheduled_at ha pasado
- FR-CMP-03: Resolver audiencia consultando suscriptores activos
- FR-CMP-04: Despachar campana resuelta al notifier-service via broker
- FR-CMP-05: Campana despachada cambia a estado sent
- FR-CMP-06: Si despacho falla, campana permanece pendiente para reintento

### Non-Functional Requirements

- NFR-01: GET /subscriptions p95 <= 100 ms
- NFR-02: POST/DELETE /subscriptions p95 <= 200 ms
- NFR-03: Procesamiento editorial.published hasta campana p95 <= 5 s
- NFR-04: Despacho campana (10k suscriptores) <= 60 s
- NFR-05: Uptime API >= 99.9% mensual
- NFR-06: Cero mensajes perdidos en RabbitMQ
- NFR-07: Tasa error sync Mailchimp < 1% mensual
- NFR-08: Minimo 3 reintentos con backoff exponencial
- NFR-09: JWT obligatorio en todas las operaciones
- NFR-10: Cero emails o tokens en logs
- NFR-11: Cobertura tests >= 80%
- NFR-12: Domain sin dependencias de framework
- NFR-13: Logging estructurado con correlation ID
- NFR-14: Health check con validacion de PostgreSQL y RabbitMQ
- NFR-15: Workers escalables horizontalmente sin duplicados

### Additional Requirements (Architecture)

- AD-01: Tres capas Domain/Application/Infrastructure
- AD-02: Soft delete con indice unico compuesto
- AD-03: JWT custom authenticator en Symfony Security
- AD-04: SyncAudience via Symfony Messenger con retry
- AD-05: Campaign state machine (pending -> processing -> sent | failed)
- AD-06: Despacho a notifier via RabbitMQ (no HTTP)
- AD-07: RFC 7807 para respuestas de error

### FR Coverage Map

| FR | Epic | Story |
|----|------|-------|
| FR-SUB-01 | E1 | 1.3 |
| FR-SUB-02 | E1 | 1.4 |
| FR-SUB-03 | E1 | 1.4 |
| FR-SUB-04 | E1 | 1.5 |
| FR-SUB-05 | E1 | 1.2 |
| FR-SUB-06 | E1 | 1.3, 1.4 |
| FR-SUB-07 | E1 | 1.3 |
| FR-AUD-01 | E2 | 2.2 |
| FR-AUD-02 | E2 | 2.3 |
| FR-AUD-03 | E2 | 2.1 |
| FR-AUD-04 | E2 | 2.1 |
| FR-AUD-05 | E2 | 2.4 |
| FR-EDT-01 | E3 | 3.1 |
| FR-EDT-02 | E3 | 3.2 |
| FR-EDT-03 | E3 | 3.3 |
| FR-EDT-04 | E3 | 3.3 |
| FR-CMP-01 | E3 | 3.3 |
| FR-CMP-02 | E4 | 4.1 |
| FR-CMP-03 | E4 | 4.2 |
| FR-CMP-04 | E4 | 4.3 |
| FR-CMP-05 | E4 | 4.1 |
| FR-CMP-06 | E4 | 4.4 |

## Epic List

| Epic | Titulo | Objetivo | Stories |
|------|--------|----------|---------|
| E1 | API de Suscripciones | Lector puede seguir/dejar de seguir entidades editoriales via REST | 5 |
| E2 | Sincronizacion de Audiencias | Mantener audiencias Mailchimp sincronizadas con suscripciones locales | 4 |
| E3 | Procesamiento de Editoriales | Consumir eventos editoriales y crear campanas de notificacion | 4 |
| E4 | Despacho de Campanas | Resolver audiencias y enviar campanas al notifier-service | 5 |
| E5 | Observabilidad y Operaciones | Monitorear, diagnosticar y operar el servicio en produccion | 3 |

---

## Epic 1: API de Suscripciones

Permitir que los lectores registrados consulten, creen y cancelen suscripciones a entidades editoriales via API REST con autenticacion JWT.

### Story 1.1: Scaffolding del proyecto y modelo de dominio

As a desarrollador,
I want la estructura base del proyecto con entidades de dominio y persistencia configurada,
So that pueda construir features sobre una base solida con las convenciones definidas.

**Acceptance Criteria:**

**Given** un proyecto Symfony 7.2 nuevo
**When** se inicializa la estructura
**Then** existen las capas src/Domain, src/Application, src/Infrastructure
**And** la entidad Subscription tiene campos: id, user_id, email, entity_type, entity_id, status, created_at, updated_at
**And** la entidad Campaign tiene campos: id, type, status, scheduled_at, audience_criteria, editorial_id, created_at, updated_at
**And** EntityType es un enum con valores: journalist, tag, section
**And** CampaignStatus es un enum con valores: pending, processing, sent, failed
**And** el mapeo Doctrine usa XML (config/doctrine/)
**And** existe migracion para crear tablas subscriptions y campaigns
**And** subscriptions tiene indice unico en (user_id, entity_type, entity_id)
**And** Domain no importa nada de Infrastructure ni de Symfony (NFR-12)

### Story 1.2: Autenticacion JWT

As a sistema,
I want validar tokens JWT en cada peticion a la API de suscripciones,
So that solo usuarios autenticados puedan operar sobre suscripciones.

**Acceptance Criteria:**

**Given** una peticion sin cabecera Authorization
**When** llega a cualquier endpoint de /subscriptions
**Then** responde 401 con formato RFC 7807

**Given** una peticion con JWT invalido o expirado
**When** llega a cualquier endpoint de /subscriptions
**Then** responde 401 con formato RFC 7807

**Given** una peticion con JWT valido
**When** se procesa la autenticacion
**Then** user_id y email estan disponibles como claims en el contexto de seguridad

### Story 1.3: Consultar estado de suscripcion (GET)

As a lector registrado,
I want consultar si estoy suscrito a una entidad editorial,
So that el frontend pueda mostrar el boton correcto ("Seguir" o "Dejar de seguir").

**Acceptance Criteria:**

**Given** un usuario autenticado con suscripcion activa para journalist/123
**When** envia GET /subscriptions/journalist/123
**Then** responde 200 con `{"subscribed": true}`

**Given** un usuario autenticado sin suscripcion (o con suscripcion inactiva) para tag/456
**When** envia GET /subscriptions/tag/456
**Then** responde 200 con `{"subscribed": false}`

**Given** un usuario autenticado
**When** envia GET /subscriptions/invalid_type/123
**Then** responde 400 con formato RFC 7807 indicando tipo no soportado

**Given** carga normal
**When** se mide latencia del endpoint
**Then** p95 <= 100 ms (NFR-01)

### Story 1.4: Crear suscripcion (POST)

As a lector registrado,
I want suscribirme a una entidad editorial,
So that pueda recibir notificaciones cuando se publique contenido relacionado.

**Acceptance Criteria:**

**Given** un usuario autenticado sin suscripcion previa para section/789
**When** envia POST /subscriptions con body `{"entityType": "section", "entityId": "789"}`
**Then** responde 200 OK
**And** se crea registro en subscriptions con status: active, user_id y email del JWT

**Given** un usuario autenticado con suscripcion inactiva para journalist/123
**When** envia POST /subscriptions con body `{"entityType": "journalist", "entityId": "123"}`
**Then** responde 200 OK
**And** el registro existente cambia a status: active (no se crea duplicado — FR-SUB-03)

**Given** un usuario autenticado con suscripcion activa para tag/456
**When** envia POST /subscriptions con body `{"entityType": "tag", "entityId": "456"}`
**Then** responde 200 OK (idempotente)

**Given** un usuario autenticado
**When** envia POST /subscriptions con entityType no soportado
**Then** responde 400 con formato RFC 7807

**Given** carga normal
**When** se mide latencia del endpoint
**Then** p95 <= 200 ms (NFR-02)

### Story 1.5: Cancelar suscripcion (DELETE)

As a lector registrado,
I want dejar de seguir una entidad editorial,
So that no reciba mas notificaciones sobre esa entidad.

**Acceptance Criteria:**

**Given** un usuario autenticado con suscripcion activa para journalist/123
**When** envia DELETE /subscriptions/journalist/123
**Then** responde 200 OK
**And** el registro cambia a status: inactive (soft delete — no se elimina fisicamente)

**Given** un usuario autenticado sin suscripcion para tag/999
**When** envia DELETE /subscriptions/tag/999
**Then** responde 404 con formato RFC 7807

**Given** carga normal
**When** se mide latencia del endpoint
**Then** p95 <= 200 ms (NFR-02)

---

## Epic 2: Sincronizacion de Audiencias

Mantener las audiencias de Mailchimp Marketing sincronizadas con las suscripciones locales de forma asincrona y resiliente.

### Story 2.1: Infraestructura de mensajeria asincrona para audiencias

As a sistema,
I want despachar operaciones de audiencia como mensajes asincronos,
So that la sincronizacion con Mailchimp no bloquee las respuestas al usuario.

**Acceptance Criteria:**

**Given** configuracion de Symfony Messenger
**When** se define el mensaje SyncAudience y su transport
**Then** existe un transport dedicado para audiencias en RabbitMQ
**And** el mensaje SyncAudience contiene: action (add/remove), user_id, email, entity_type, entity_id
**And** el handler de SyncAudience invoca MailchimpClientInterface
**And** un fallo en el handler no afecta el estado de la suscripcion local (FR-AUD-04)

### Story 2.2: Anadir usuario a audiencia Mailchimp al suscribirse

As a sistema,
I want anadir al usuario a la audiencia Mailchimp correspondiente al crear una suscripcion,
So that el usuario quede registrado para recibir campanas de marketing.

**Acceptance Criteria:**

**Given** un usuario que acaba de crear/reactivar una suscripcion (POST /subscriptions exitoso)
**When** el handler de suscripcion completa
**Then** se despacha mensaje SyncAudience con action: add
**And** MailchimpClient invoca la API de Mailchimp Marketing para anadir member a la lista correspondiente

### Story 2.3: Eliminar usuario de audiencia Mailchimp al cancelar

As a sistema,
I want eliminar al usuario de la audiencia Mailchimp al desactivar una suscripcion,
So that el usuario no reciba campanas de marketing para esa entidad.

**Acceptance Criteria:**

**Given** un usuario que acaba de desactivar una suscripcion (DELETE exitoso)
**When** el handler de suscripcion completa
**Then** se despacha mensaje SyncAudience con action: remove
**And** MailchimpClient invoca la API de Mailchimp Marketing para eliminar member de la lista

### Story 2.4: Reintentos y resiliencia en sincronizacion de audiencias

As a sistema,
I want reintentar automaticamente operaciones fallidas de audiencia,
So that fallos transitorios de Mailchimp no generen inconsistencias permanentes.

**Acceptance Criteria:**

**Given** un fallo transitorio de la API de Mailchimp
**When** el handler de SyncAudience falla
**Then** Messenger reintenta 3 veces con backoff exponencial (1s, 4s, 16s — NFR-08)

**Given** 3 reintentos fallidos consecutivos
**When** se agota el retry
**Then** el mensaje se envia a dead letter queue
**And** se genera log de error con correlation ID (NFR-13)
**And** la tasa de error mensual se monitorea (objetivo < 1% — NFR-07)

---

## Epic 3: Procesamiento de Editoriales

Consumir eventos de publicacion editorial, verificar relevancia y crear campanas de notificacion.

### Story 3.1: Consumer de eventos editorial.published

As a sistema,
I want consumir mensajes editorial.published de RabbitMQ,
So that enBandeja reaccione automaticamente cuando el CMS publica contenido.

**Acceptance Criteria:**

**Given** configuracion de Symfony Messenger con transport para editorial events
**When** el CMS publica un mensaje editorial.published en RabbitMQ
**Then** EditorialPublishedHandler consume el mensaje
**And** el payload contiene: editorial_id, journalist_id, tag_ids[], section_ids[], title, url

**Given** un mensaje consumido exitosamente
**When** el handler completa (exito o descarte)
**Then** se hace ACK del mensaje (NFR-06: cero mensajes perdidos)

**Given** un mensaje con payload invalido
**When** el handler intenta procesarlo
**Then** se registra error con correlation ID y se hace ACK (no reintento de mensajes malformados)

### Story 3.2: Verificacion de flags de habilitacion

As a sistema,
I want verificar si las entidades del editorial tienen habilitadas las notificaciones,
So that solo se generen campanas para entidades que han optado por participar.

**Acceptance Criteria:**

**Given** un editorial con journalist_id: 123
**When** el handler consulta journalist-svc
**Then** envia GET al servicio con timeout de 2 s
**And** recibe {enabled: true/false}

**Given** un editorial con tag_ids: [1, 2, 3]
**When** el handler consulta tag-svc para cada tag
**Then** recibe flag de habilitacion por cada tag

**Given** un editorial con section_ids: [10]
**When** el handler consulta section-svc
**Then** recibe flag de habilitacion para la seccion

**Given** un servicio externo que no responde dentro del timeout (2 s)
**When** el handler espera respuesta
**Then** asume flag deshabilitado para esa entidad (fail-safe)

### Story 3.3: Creacion de campana o descarte

As a sistema,
I want crear una campana si hay entidades con seguidores activos, o descartar el evento si no,
So that solo se procesen notificaciones cuando hay audiencia real.

**Acceptance Criteria:**

**Given** un editorial cuyas entidades habilitadas tienen al menos un suscriptor activo
**When** el handler verifica seguidores
**Then** crea registro en campaigns con: type, status: pending, scheduled_at, audience_criteria (entidades con seguidores), editorial_id
**And** se genera log de campana creada con correlation ID

**Given** un editorial cuyas entidades habilitadas no tienen ningun suscriptor activo
**When** el handler verifica seguidores
**Then** descarta el evento sin crear campana
**And** se genera log de descarte con correlation ID

**Given** procesamiento completo (creacion o descarte)
**When** se mide tiempo desde recepcion del mensaje
**Then** p95 <= 5 s (NFR-03)

### Story 3.4: Clients HTTP para servicios externos

As a desarrollador,
I want implementaciones concretas de los clients para journalist-svc, tag-svc y section-svc,
So that el EditorialHandler pueda verificar flags de habilitacion.

**Acceptance Criteria:**

**Given** configuracion de servicios externos
**When** se implementan JournalistServiceClient, TagServiceClient, SectionServiceClient
**Then** cada client implementa su interface de dominio
**And** usa Symfony HttpClient con timeout de 2 s
**And** traduce errores HTTP en excepciones de dominio (TransientErrorException, PermanentErrorException)
**And** los tests unitarios mockean las interfaces sin depender de servicios reales

---

## Epic 4: Despacho de Campanas

Procesar campanas programadas, resolver audiencias y despachar notificaciones al notifier-service.

### Story 4.1: Worker de campanas programadas

As a sistema,
I want un worker que procese campanas pendientes cuya hora programada ha pasado,
So that las notificaciones se envien en el momento adecuado.

**Acceptance Criteria:**

**Given** una campana con status: pending y scheduled_at <= NOW()
**When** el worker ejecuta su ciclo
**Then** la campana cambia a status: processing
**And** se inicia el procesamiento de la campana

**Given** una campana con status: pending y scheduled_at > NOW()
**When** el worker ejecuta su ciclo
**Then** la campana no se procesa (permanece pending)

**Given** multiples workers ejecutando en paralelo
**When** ambos detectan la misma campana pendiente
**Then** solo uno la procesa (NFR-15: sin procesamiento duplicado)
**And** se usa SELECT FOR UPDATE SKIP LOCKED o mecanismo equivalente

### Story 4.2: Resolucion de audiencia

As a sistema,
I want resolver la lista de destinatarios de una campana consultando suscriptores activos,
So that solo se notifique a usuarios con suscripciones vigentes.

**Acceptance Criteria:**

**Given** una campana en processing con audience_criteria que referencia journalist/123 y tag/456
**When** se resuelve la audiencia
**Then** se consultan suscriptores con status: active para esas entidades
**And** se deduplican destinatarios (un usuario suscrito a multiples entidades recibe un solo email)
**And** el resultado contiene: lista de {user_id, email} unicos

### Story 4.3: Despacho a notifier-service

As a sistema,
I want enviar la campana resuelta al notifier-service via RabbitMQ,
So that los emails se envien sin acoplamiento directo con Mailchimp Transactional.

**Acceptance Criteria:**

**Given** una campana con audiencia resuelta (lista de destinatarios + contenido)
**When** el handler despacha
**Then** publica mensaje en RabbitMQ con: recipients[], editorial_id, content
**And** la campana cambia a status: sent (FR-CMP-05)

**Given** despacho completo para campana de hasta 10 000 suscriptores
**When** se mide tiempo desde scheduled_at
**Then** <= 60 s (NFR-04)

### Story 4.4: Manejo de fallos en despacho

As a sistema,
I want que campanas fallidas permanezcan en estado pendiente para reintento,
So that ningun lector pierda una notificacion por un fallo transitorio.

**Acceptance Criteria:**

**Given** una campana en processing
**When** falla la publicacion del mensaje a RabbitMQ
**Then** la campana vuelve a status: pending (FR-CMP-06)
**And** se genera log de error con correlation ID

**Given** una campana que ha fallado multiples veces
**When** se revisa el estado
**Then** un mecanismo evita reintentos infinitos (max retries o cambio a status: failed tras N intentos)

### Story 4.5: Comando de reconciliacion de campanas

As a operador,
I want un comando CLI para reconciliar campanas atascadas,
So that pueda resolver manualmente situaciones excepcionales.

**Acceptance Criteria:**

**Given** campanas en status: processing por mas de 10 minutos
**When** se ejecuta `bin/console app:reconcile-campaigns`
**Then** las campanas atascadas vuelven a status: pending para reprocesamiento
**And** se genera log de reconciliacion

---

## Epic 5: Observabilidad y Operaciones

Dotar al servicio de las herramientas necesarias para monitoreo, diagnostico y operacion en produccion.

### Story 5.1: Health check endpoint

As a operador,
I want un endpoint /health que valide la conectividad con dependencias criticas,
So that el balanceador de carga y los sistemas de monitoreo detecten problemas.

**Acceptance Criteria:**

**Given** PostgreSQL y RabbitMQ accesibles
**When** se invoca GET /health
**Then** responde 200 con `{"status": "ok", "checks": {"database": "ok", "rabbitmq": "ok"}}`

**Given** PostgreSQL inaccesible
**When** se invoca GET /health
**Then** responde 503 con `{"status": "degraded", "checks": {"database": "error", "rabbitmq": "ok"}}`

**Given** el endpoint /health
**When** se evalua seguridad
**Then** no requiere autenticacion JWT (es publico para el balanceador)

### Story 5.2: Logging estructurado con correlation ID

As a operador,
I want logs estructurados con correlation ID en todas las operaciones criticas,
So that pueda rastrear el flujo completo de una peticion o campana.

**Acceptance Criteria:**

**Given** una peticion HTTP a la API de suscripciones
**When** se procesa la peticion
**Then** todos los logs incluyen: timestamp, level, message, correlation_id, user_id (si disponible)
**And** cero emails o tokens en campos de log (NFR-10)

**Given** un mensaje consumido de RabbitMQ (editorial.published o SendCampaign)
**When** se procesa el mensaje
**Then** todos los logs incluyen correlation_id propagado desde el mensaje

### Story 5.3: Configuracion Docker y entorno de desarrollo

As a desarrollador,
I want una configuracion Docker completa para desarrollo local,
So that pueda ejecutar el servicio completo con todas sus dependencias.

**Acceptance Criteria:**

**Given** el repositorio clonado
**When** se ejecuta `docker compose up`
**Then** levanta: PHP-FPM, PostgreSQL, RabbitMQ
**And** las migraciones se aplican automaticamente
**And** el servicio responde en el puerto configurado
**And** los tests se pueden ejecutar con `docker compose exec app bin/phpunit`
