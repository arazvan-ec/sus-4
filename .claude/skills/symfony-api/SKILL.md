# Symfony API Patterns

## When to Apply
Use when implementing REST API endpoints in Symfony controllers.

## Patterns

### Controller Structure
- Controllers in `src/Infrastructure/Controller/`
- Final classes, one public method per controller (invokable)
- Inject services via constructor, not via method arguments
- Return `JsonResponse` always

### Request/Response
- Parse request body: `json_decode($request->getContent(), true)`
- Validate input at controller level, delegate logic to application service
- Success responses: 200 (GET), 201 (POST with Location header), 204 (DELETE)

### Error Responses — RFC 7807
All errors return `application/problem+json`:

```json
{
    "type": "https://httpstatuses.io/404",
    "title": "Not Found",
    "status": 404,
    "detail": "Editorial ed-123 not found"
}
```

### Route Naming
- `GET /editorials/{id}` -> `app_editorial_show`
- `POST /editorials` -> `app_editorial_create`
- `GET /health` -> `app_health`

### Health Endpoint
Every service must have `GET /health` returning:
```json
{"status": "ok", "timestamp": "2026-03-25T10:00:00Z"}
```
