# Product Brief: enBandeja-service

## Resumen

Microservicio que gestiona suscripciones de usuarios a entidades editoriales (periodistas, tags, secciones) y orquesta el envio de notificaciones cuando se publica contenido relevante.

## Contexto del sistema

| Componente | Responsabilidad |
|------------|----------------|
| delorean-statics (JS) | Frontend. Lee JWT de cookie, llama a enBandeja via REST |
| enBandeja-service | Dominio completo: suscripciones + audiencias + campanas |
| Legacy / Jarvis (CMS) | Publica editoriales, emite evento `editorial.published` via RabbitMQ |
| notifier-service | Solo envio: Mailchimp Transactional |

---

## Flujo A: Suscripcion (sincrono, REST)

### 1. CDN sirve pagina cacheada con placeholder del boton
- Transparent Edge -> HTML sin personalizar -> boton oculto o generico

### 2. JS detecta usuario logado y consulta estado
- delorean-statics lee `accessToken` (cookie)
- `GET /subscriptions/{entityType}/{entityId}` con JWT
- enBandeja valida JWT -> consulta subscriptions
- Respuesta: `{subscribed: true/false}`
- Frontend pinta "Seguir" o "Dejar de seguir"

### 3a. Usuario pulsa "Seguir"
- `POST /subscriptions` con JWT
- Body: `{entityType, entityId}`
- enBandeja crea subscription con `status: active`
- Respuesta: `200 OK`
- Frontend cambia boton a "Dejar de seguir"

### 3b. Usuario pulsa "Dejar de seguir"
- `DELETE /subscriptions/{entityType}/{entityId}` con JWT
- enBandeja marca subscription como `status: inactive` (soft delete)
- Respuesta: `200 OK`
- Frontend cambia boton a "Seguir"

### 4. Async: sincronizacion con Mailchimp
- POST -> anade a audiencia (Mailchimp Marketing API)
- DELETE -> elimina de audiencia (Mailchimp Marketing API)
- No bloquea al usuario

---

## Flujo B: Notificacion por Editorial (asincrono, eventos)

### 1. Publicacion
- Redactor publica editorial en Legacy/Jarvis (CMS)
- CMS emite evento `editorial.published` -> RabbitMQ

### 2. EditorialHandler (enBandeja-service)
1. Consume mensaje `editorial.published`
2. Verifica flags habilitados consultando servicios externos:
   - journalist-svc
   - tag-svc
   - section-svc
3. Decision: alguna entidad tiene seguidores?
   - **No** -> Descarta el mensaje
   - **Si** -> Continua

### 3. Creacion de campana
- Crea registro en tabla `campaigns` con:
  - `type`
  - `status`
  - `scheduled_at`
  - `audience_criteria`
  - `editorial_id`

### 4. Worker (cuando `scheduled_at <= NOW()`)
1. Resuelve suscriptores (datos frescos)
   - Consulta tabla `subscriptions` filtrando solo `status: active`
2. Despacha a notifier-service
   - Destinatarios + contenido -> RabbitMQ

### 5. Envio
- notifier-service -> Mailchimp Transactional (solo envio)

---

## Modelo de datos

### subscriptions
| Campo | Tipo | Notas |
|-------|------|-------|
| user_id | string/uuid | ID del usuario |
| email | string | Email del usuario |
| entity_type | string | journalist, tag, section |
| entity_id | string | ID de la entidad seguida |
| status | enum | active, inactive |

### campaigns
| Campo | Tipo | Notas |
|-------|------|-------|
| type | string | Tipo de campana |
| status | enum | Estado del procesamiento |
| scheduled_at | datetime | Cuando se debe procesar |
| audience_criteria | json/string | Criterios para resolver audiencia |
| editorial_id | string | ID del editorial que origino la campana |

---

## API REST

| Metodo | Endpoint | Auth | Descripcion |
|--------|----------|------|-------------|
| GET | `/subscriptions/{entityType}/{entityId}` | JWT | Consultar si usuario esta suscrito |
| POST | `/subscriptions` | JWT | Crear suscripcion (status: active) |
| DELETE | `/subscriptions/{entityType}/{entityId}` | JWT | Soft delete (status: inactive) |

---

## Integraciones externas

| Servicio | Protocolo | Uso |
|----------|-----------|-----|
| Mailchimp Marketing API | HTTP async | Sync audiencias (add/remove) |
| Mailchimp Transactional | HTTP (via notifier) | Envio de emails |
| journalist-svc | HTTP | Verificar flags de periodistas |
| tag-svc | HTTP | Verificar flags de tags |
| section-svc | HTTP | Verificar flags de secciones |
| RabbitMQ | AMQP | Consumir `editorial.published`, despachar a notifier |

---

## Limites de responsabilidad

- **delorean-statics -> enBandeja directo**: Auth via JWT (cookie)
- **enBandeja**: dominio completo — Suscripciones + audiencias + campanas
- **notifier**: solo envio — Mailchimp Transactional
