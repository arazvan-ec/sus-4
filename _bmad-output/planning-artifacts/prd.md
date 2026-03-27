---
stepsCompleted: [step-01-init, step-02-discovery, step-02b-vision, step-02c-executive-summary, step-03-success, step-04-journeys, step-05-domain, step-06-innovation, step-07-project-type, step-08-scoping, step-09-functional, step-10-nonfunctional, step-11-polish, step-12-complete]
inputDocuments: [product-brief-enbandeja-service.md, project-context.md]
workflowType: 'prd'
---

# PRD: enBandeja-service

## 1. Resumen Ejecutivo

### Vision

enBandeja es el microservicio que cierra el ciclo entre publicacion editorial y engagement del lector. Permite a usuarios suscribirse a entidades editoriales (periodistas, tags, secciones) y recibir notificaciones por email cuando se publica contenido relevante asociado a esas entidades.

### Diferenciador

- **Dominio unificado**: suscripciones, audiencias y campanas en un unico servicio con responsabilidad clara.
- **Arquitectura dual**: REST sincrono para interaccion del usuario + eventos asincronos para procesamiento editorial.
- **Desacoplamiento de envio**: enBandeja orquesta; notifier-service envia. Sin dependencia directa con Mailchimp Transactional.
- **Compatible con CDN**: El frontend funciona sobre paginas cacheadas; la personalizacion ocurre exclusivamente en cliente via JS + JWT.

### Usuarios objetivo

| Segmento | Descripcion |
|----------|-------------|
| Lector registrado | Usuario con sesion activa (JWT) que sigue entidades editoriales |
| Redactor / Editor | Publica editoriales en el CMS; no interactua directamente con enBandeja |
| Sistema (CMS Legacy/Jarvis) | Emite eventos `editorial.published` que disparan el flujo de notificacion |

---

## 2. Criterios de Exito

Todos los criterios son medibles y verificables en entorno de produccion.

| ID | Criterio | Metrica | Objetivo | Plazo |
|----|----------|---------|----------|-------|
| SC-01 | Latencia de consulta de suscripcion | p95 de GET /subscriptions/{entityType}/{entityId} | <= 100 ms | MVP |
| SC-02 | Latencia de creacion/eliminacion de suscripcion | p95 de POST y DELETE /subscriptions | <= 200 ms | MVP |
| SC-03 | Tiempo de procesamiento de evento editorial | Desde recepcion de `editorial.published` hasta creacion de campana | <= 5 s (p95) | MVP |
| SC-04 | Tiempo de despacho de campana | Desde `scheduled_at` hasta envio completo al notifier-service | <= 60 s para campanas de hasta 10 000 suscriptores | MVP |
| SC-05 | Disponibilidad del API REST | Uptime mensual | >= 99.9% | MVP |
| SC-06 | Tasa de error en sync con Mailchimp Marketing | Porcentaje de operaciones fallidas de audiencia | < 1% mensual | MVP |
| SC-07 | Cobertura de tests automatizados | Lineas cubiertas por tests unitarios e integracion | >= 80% | MVP |

---

## 3. Alcance del Producto

### Fase MVP

- API REST de suscripciones (GET, POST, DELETE) con autenticacion JWT.
- Modelo de datos: `subscriptions` y `campaigns` en PostgreSQL.
- Consumo de evento `editorial.published` via RabbitMQ.
- Verificacion de flags contra journalist-svc, tag-svc, section-svc.
- Creacion de campanas con scheduling.
- Worker que resuelve audiencias y despacha a notifier-service via RabbitMQ.
- Sincronizacion asincrona de audiencias con Mailchimp Marketing API.

### Fase Growth

- Soporte para nuevos tipos de entidad (columnas, blogs, especiales).
- Preferencias de frecuencia de notificacion por usuario (inmediata, diaria, semanal).
- Metricas de engagement por campana (aperturas, clicks via webhooks de Mailchimp).
- API de administracion para consultar campanas y estadisticas.

### Fase Vision

- Recomendaciones automaticas de suscripcion basadas en habitos de lectura.
- Segmentacion avanzada de audiencias con criterios combinados.
- Soporte multicanal (push, in-app) ademas de email.

---

## 4. Recorridos de Usuario

### J-01: Lector consulta estado de suscripcion

**Actor**: Lector registrado
**Precondicion**: Usuario con sesion activa (JWT valido en cookie)
**Trigger**: Pagina cacheada se carga en navegador

1. CDN sirve HTML cacheado con placeholder de boton (oculto/generico).
2. JS (delorean-statics) detecta JWT en cookie.
3. JS envia `GET /subscriptions/{entityType}/{entityId}` con JWT en cabecera Authorization.
4. enBandeja valida JWT, consulta tabla `subscriptions`.
5. enBandeja responde `{subscribed: true}` o `{subscribed: false}`.
6. JS muestra boton "Seguir" o "Dejar de seguir" segun respuesta.

**Postcondicion**: Boton refleja estado real de suscripcion del usuario.
**Error**: JWT invalido o expirado -> respuesta 401 -> JS no muestra boton.

### J-02: Lector se suscribe a entidad

**Actor**: Lector registrado
**Precondicion**: J-01 completado, boton muestra "Seguir"
**Trigger**: Usuario pulsa "Seguir"

1. JS envia `POST /subscriptions` con JWT, body `{entityType, entityId}`.
2. enBandeja valida JWT, crea o reactiva registro en `subscriptions` con `status: active`.
3. enBandeja responde 200 OK.
4. JS cambia boton a "Dejar de seguir".
5. (Async) enBandeja anade al usuario a la audiencia correspondiente en Mailchimp Marketing API.

**Postcondicion**: Registro `subscriptions` con `status: active`. Usuario en audiencia de Mailchimp.
**Error**: Fallo en Mailchimp -> se reintenta asincrona; la suscripcion local queda activa.

### J-03: Lector cancela suscripcion

**Actor**: Lector registrado
**Precondicion**: J-01 completado, boton muestra "Dejar de seguir"
**Trigger**: Usuario pulsa "Dejar de seguir"

1. JS envia `DELETE /subscriptions/{entityType}/{entityId}` con JWT.
2. enBandeja valida JWT, marca registro como `status: inactive` (soft delete).
3. enBandeja responde 200 OK.
4. JS cambia boton a "Seguir".
5. (Async) enBandeja elimina al usuario de la audiencia en Mailchimp Marketing API.

**Postcondicion**: Registro `subscriptions` con `status: inactive`. Usuario removido de audiencia Mailchimp.

### J-04: Editorial publicado genera notificacion

**Actor**: Sistema (CMS Legacy/Jarvis)
**Precondicion**: Redactor publica editorial en CMS
**Trigger**: CMS emite evento `editorial.published` a RabbitMQ

1. EditorialHandler de enBandeja consume el mensaje `editorial.published`.
2. Handler extrae entidades asociadas (periodista, tags, secciones).
3. Handler consulta flags habilitados en journalist-svc, tag-svc, section-svc.
4. Para cada entidad con flag habilitado, verifica si existen suscriptores activos.
5. Si no hay suscriptores activos para ninguna entidad -> descarta mensaje. Fin.
6. Si hay suscriptores -> crea registro en `campaigns` con `type`, `status: pending`, `scheduled_at`, `audience_criteria`, `editorial_id`.

**Postcondicion**: Campana creada en estado `pending` con scheduling configurado.

### J-05: Worker procesa campana y despacha notificaciones

**Actor**: Sistema (Worker interno)
**Precondicion**: Campana en estado `pending` con `scheduled_at <= NOW()`
**Trigger**: Worker ejecuta polling periodico

1. Worker selecciona campanas pendientes cuyo `scheduled_at` ha pasado.
2. Resuelve audiencia: consulta `subscriptions` con `status: active` segun `audience_criteria`.
3. Compone payload con destinatarios y contenido editorial.
4. Despacha mensaje a notifier-service via RabbitMQ.
5. Actualiza estado de campana a `sent`.

**Postcondicion**: Mensaje despachado a notifier-service. Campana en estado `sent`.
**Error**: Fallo al despachar -> campana permanece en estado `pending` para reintento.

---

## 5. Requisitos Funcionales

Cada requisito describe una capacidad observable y verificable. Sin detalle de implementacion.

### 5.1 Suscripciones

| ID | Requisito |
|----|-----------|
| FR-SUB-01 | El sistema debe permitir consultar si un usuario esta suscrito a una entidad dado `entityType` y `entityId`. |
| FR-SUB-02 | El sistema debe permitir crear una suscripcion asociando un usuario a una entidad con `entityType` y `entityId`. |
| FR-SUB-03 | Si el usuario ya tiene una suscripcion inactiva para la misma entidad, la creacion debe reactivarla en vez de crear un duplicado. |
| FR-SUB-04 | El sistema debe permitir desactivar una suscripcion (soft delete), cambiando su estado a inactivo sin eliminar el registro. |
| FR-SUB-05 | Todas las operaciones de suscripcion requieren autenticacion via JWT. Peticiones sin JWT valido deben recibir respuesta 401. |
| FR-SUB-06 | Los tipos de entidad soportados son: `journalist`, `tag`, `section`. Peticiones con tipos no soportados deben recibir respuesta 400. |
| FR-SUB-07 | La respuesta de consulta de suscripcion debe incluir un campo booleano indicando el estado de suscripcion. |

### 5.2 Sincronizacion de audiencias

| ID | Requisito |
|----|-----------|
| FR-AUD-01 | Al crear o reactivar una suscripcion, el sistema debe anadir al usuario a la audiencia correspondiente en el servicio externo de marketing por email. |
| FR-AUD-02 | Al desactivar una suscripcion, el sistema debe eliminar al usuario de la audiencia correspondiente en el servicio externo de marketing por email. |
| FR-AUD-03 | La sincronizacion de audiencias no debe bloquear la respuesta al usuario en las operaciones de suscripcion. |
| FR-AUD-04 | Los fallos en la sincronizacion de audiencias no deben afectar el estado de la suscripcion local. |
| FR-AUD-05 | Los fallos en la sincronizacion de audiencias deben reintentarse automaticamente. |

### 5.3 Procesamiento de editoriales

| ID | Requisito |
|----|-----------|
| FR-EDT-01 | El sistema debe consumir eventos `editorial.published` del broker de mensajeria. |
| FR-EDT-02 | Para cada editorial publicado, el sistema debe verificar los flags de habilitacion de las entidades asociadas consultando los servicios externos correspondientes (periodistas, tags, secciones). |
| FR-EDT-03 | Si ninguna entidad asociada al editorial tiene seguidores activos, el sistema debe descartar el evento sin crear campana. |
| FR-EDT-04 | Si al menos una entidad tiene seguidores activos y flag habilitado, el sistema debe crear una campana. |

### 5.4 Campanas

| ID | Requisito |
|----|-----------|
| FR-CMP-01 | Cada campana debe registrar: tipo, estado, fecha de programacion, criterios de audiencia e identificador del editorial. |
| FR-CMP-02 | El sistema debe procesar campanas pendientes cuya fecha de programacion sea igual o anterior al momento actual. |
| FR-CMP-03 | Al procesar una campana, el sistema debe resolver la audiencia consultando suscriptores activos segun los criterios almacenados. |
| FR-CMP-04 | El sistema debe despachar los datos de la campana resuelta (destinatarios + contenido) al servicio de notificaciones via broker de mensajeria. |
| FR-CMP-05 | Una campana procesada y despachada debe cambiar a estado `sent`. |
| FR-CMP-06 | Si el despacho de una campana falla, la campana debe permanecer en estado pendiente para reintento posterior. |

---

## 6. Requisitos No Funcionales

| ID | Categoria | Requisito | Metrica |
|----|-----------|-----------|---------|
| NFR-01 | Rendimiento | Latencia de GET /subscriptions | p95 <= 100 ms |
| NFR-02 | Rendimiento | Latencia de POST/DELETE /subscriptions | p95 <= 200 ms |
| NFR-03 | Rendimiento | Procesamiento de evento `editorial.published` hasta creacion de campana | p95 <= 5 s |
| NFR-04 | Rendimiento | Despacho de campana (hasta 10 000 suscriptores) | <= 60 s |
| NFR-05 | Disponibilidad | Uptime del API REST | >= 99.9% mensual |
| NFR-06 | Disponibilidad | Procesamiento de mensajes RabbitMQ sin perdida | 0 mensajes perdidos (ACK tras procesamiento exitoso) |
| NFR-07 | Fiabilidad | Tasa de error en sync con Mailchimp Marketing | < 1% mensual |
| NFR-08 | Fiabilidad | Reintentos automaticos en fallos de integraciones externas | Minimo 3 reintentos con backoff exponencial |
| NFR-09 | Seguridad | Autenticacion de API REST | JWT obligatorio en todas las operaciones de suscripcion |
| NFR-10 | Seguridad | Datos sensibles en logs | Cero emails o tokens en logs de aplicacion |
| NFR-11 | Mantenibilidad | Cobertura de tests | >= 80% lineas |
| NFR-12 | Mantenibilidad | Arquitectura en capas | Domain sin dependencias de framework ni infraestructura |
| NFR-13 | Observabilidad | Logging estructurado | Todas las operaciones criticas (suscripcion, campana, despacho) generan log con correlation ID |
| NFR-14 | Observabilidad | Health check | Endpoint /health que valida conectividad con PostgreSQL y RabbitMQ |
| NFR-15 | Escalabilidad | Workers de campana | Escalables horizontalmente sin procesamiento duplicado |

---

## 7. Integraciones Externas

| ID | Servicio | Protocolo | Direccion | Proposito |
|----|----------|-----------|-----------|-----------|
| INT-01 | Mailchimp Marketing API | HTTP (REST) | Saliente, asincrono | Anadir/eliminar usuarios de audiencias al crear/cancelar suscripciones |
| INT-02 | notifier-service | RabbitMQ (AMQP) | Saliente, asincrono | Despachar campanas resueltas (destinatarios + contenido) para envio de email |
| INT-03 | Legacy/Jarvis CMS | RabbitMQ (AMQP) | Entrante, asincrono | Consumir eventos `editorial.published` |
| INT-04 | journalist-svc | HTTP (REST) | Saliente, sincrono | Verificar flag de habilitacion de periodistas para notificaciones |
| INT-05 | tag-svc | HTTP (REST) | Saliente, sincrono | Verificar flag de habilitacion de tags para notificaciones |
| INT-06 | section-svc | HTTP (REST) | Saliente, sincrono | Verificar flag de habilitacion de secciones para notificaciones |
| INT-07 | delorean-statics (JS) | HTTP (REST) | Entrante, sincrono | Frontend que consume la API de suscripciones con JWT |

---

## 8. Restricciones y Supuestos

### Restricciones

| ID | Restriccion |
|----|-------------|
| CON-01 | Stack tecnologico fijo: PHP 8.4+, Symfony 7.2, Doctrine ORM, PostgreSQL. |
| CON-02 | Broker de mensajeria: RabbitMQ via Symfony Messenger. |
| CON-03 | enBandeja no envia emails directamente. Todo envio se delega a notifier-service. |
| CON-04 | La autenticacion se basa en JWT emitido por un servicio externo. enBandeja solo valida, no emite tokens. |
| CON-05 | Las paginas se sirven desde CDN (Transparent Edge) sin personalizacion server-side. La personalizacion ocurre en cliente. |
| CON-06 | Soft delete obligatorio en suscripciones. No se eliminan registros fisicamente. |

### Supuestos

| ID | Supuesto |
|----|----------|
| ASM-01 | El JWT contiene `user_id` y `email` como claims accesibles tras validacion. |
| ASM-02 | Los servicios journalist-svc, tag-svc y section-svc exponen endpoints HTTP para consultar flags de habilitacion y estan disponibles en el momento del procesamiento del evento. |
| ASM-03 | El CMS Legacy/Jarvis emite eventos `editorial.published` con estructura conocida que incluye IDs de entidades asociadas (periodista, tags, secciones). |
| ASM-04 | Mailchimp Marketing API soporta operaciones individuales de add/remove en audiencias via API key o OAuth. |
| ASM-05 | notifier-service consume mensajes del broker con formato acordado y se encarga del envio via Mailchimp Transactional. |
| ASM-06 | El volumen maximo estimado por campana es de 10 000 suscriptores en fase MVP. |
| ASM-07 | Un usuario puede suscribirse a multiples entidades de distintos tipos simultaneamente. |
