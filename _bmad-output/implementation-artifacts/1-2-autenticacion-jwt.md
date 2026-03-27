# Story 1.2: Autenticacion JWT

Status: ready-for-dev

## Story

As a sistema,
I want validar tokens JWT en cada peticion a la API de suscripciones,
so that solo usuarios autenticados puedan operar sobre suscripciones.

## Acceptance Criteria

1. Peticion sin cabecera Authorization a /subscriptions/* responde 401 con formato RFC 7807
2. Peticion con JWT invalido (firma incorrecta, malformado) responde 401 con formato RFC 7807
3. Peticion con JWT expirado responde 401 con formato RFC 7807
4. Peticion con JWT valido permite acceso y extrae user_id y email de los claims
5. user_id y email estan disponibles en el contexto de seguridad de Symfony
6. El endpoint /health NO requiere autenticacion (publico)
7. Respuestas 401 no revelan detalles internos de la validacion (seguridad)
8. Cero tokens en logs de aplicacion (NFR-10)

## Tasks / Subtasks

- [ ] Task 1: Instalar dependencia para decodificacion JWT (AC: 2, 3)
  - [ ] 1.1 `composer require firebase/php-jwt` — libreria ligera para decodificar/verificar JWT
  - [ ] 1.2 Verificar que no hay conflictos con dependencias existentes

- [ ] Task 2: Crear modelo de usuario para Symfony Security (AC: 4, 5)
  - [ ] 2.1 `src/Infrastructure/Security/JwtUser.php` — implementa `Symfony\Component\Security\Core\User\UserInterface`
  - [ ] 2.2 Almacena user_id y email extraidos del JWT
  - [ ] 2.3 Metodos `getUserId(): string` y `getEmail(): string`

- [ ] Task 3: Crear AccessTokenHandler para validacion JWT (AC: 1, 2, 3, 4)
  - [ ] 3.1 `src/Infrastructure/Security/JwtAccessTokenHandler.php` — implementa `AccessTokenHandlerInterface`
  - [ ] 3.2 Decodifica JWT usando firebase/php-jwt con clave publica/secreta configurable
  - [ ] 3.3 Valida firma, expiracion (exp), y claims requeridos (user_id, email)
  - [ ] 3.4 Retorna `UserBadge` con JwtUser en caso de exito
  - [ ] 3.5 Lanza `BadCredentialsException` en caso de fallo (sin detalles internos — AC: 7)

- [ ] Task 4: Crear listener RFC 7807 para errores de autenticacion (AC: 1, 7)
  - [ ] 4.1 `src/Infrastructure/Security/AuthenticationFailureListener.php` — escucha `AuthenticationFailureEvent`
  - [ ] 4.2 Genera respuesta 401 con formato RFC 7807:
    ```json
    {
      "type": "https://enbandeja.example.com/errors/authentication-required",
      "title": "Authentication Required",
      "status": 401,
      "detail": "Valid JWT token is required"
    }
    ```
  - [ ] 4.3 No revela causa especifica del fallo (firma invalida vs expirado vs ausente)

- [ ] Task 5: Configurar security.yaml (AC: 1, 5, 6)
  - [ ] 5.1 Configurar firewall `api` con `access_token` authenticator usando `JwtAccessTokenHandler`
  - [ ] 5.2 Patron del firewall: `^/subscriptions`
  - [ ] 5.3 Verificar que /health queda fuera del firewall (publico — AC: 6)
  - [ ] 5.4 Registrar JwtAccessTokenHandler como servicio en services.yaml con parametro de clave JWT

- [ ] Task 6: Configurar parametro de clave JWT (AC: 2, 3)
  - [ ] 6.1 Agregar `JWT_SECRET` en `.env` con valor por defecto para desarrollo
  - [ ] 6.2 Inyectar parametro en JwtAccessTokenHandler via services.yaml

- [ ] Task 7: Tests unitarios (AC: 1-8)
  - [ ] 7.1 `tests/Unit/Infrastructure/Security/JwtAccessTokenHandlerTest.php`
    - Test: JWT valido retorna UserBadge con user_id y email correctos
    - Test: JWT con firma invalida lanza BadCredentialsException
    - Test: JWT expirado lanza BadCredentialsException
    - Test: JWT sin claim user_id lanza BadCredentialsException
    - Test: JWT sin claim email lanza BadCredentialsException
  - [ ] 7.2 `tests/Unit/Infrastructure/Security/JwtUserTest.php`
    - Test: Constructor almacena user_id y email
    - Test: getUserIdentifier retorna user_id
    - Test: getRoles retorna ['ROLE_USER']

## Dev Notes

### Arquitectura — Decisiones clave

**AD-03**: enBandeja solo valida JWT, no emite tokens. La autenticacion la gestiona un servicio externo.

**Enfoque**: Usar el sistema nativo de Symfony `access_token` authentication en vez de LexikJWTAuthenticationBundle. Razones:
- No necesitamos emision de tokens (login/refresh)
- Menos dependencias
- Symfony 7.2 soporta `AccessTokenHandlerInterface` nativamente
- Mas control sobre la validacion

### Symfony Access Token Authentication

Configuracion en security.yaml:
```yaml
security:
    firewalls:
        api:
            pattern: ^/subscriptions
            stateless: true
            access_token:
                token_handler: App\Infrastructure\Security\JwtAccessTokenHandler
```

El handler implementa `AccessTokenHandlerInterface::getUserBadgeFrom(string $accessToken): UserBadge`.

### JWT Claims esperados

El JWT emitido por el servicio externo contiene al menos:
```json
{
  "sub": "user-uuid-123",
  "email": "user@example.com",
  "exp": 1711584000,
  "iat": 1711580400
}
```

Mapping: `sub` → `user_id`, `email` → `email`.

### firebase/php-jwt

```php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$decoded = JWT::decode($token, new Key($secret, 'HS256'));
// $decoded->sub, $decoded->email, $decoded->exp
```

- Valida firma automaticamente
- Valida exp automaticamente (lanza ExpiredException)
- Lanza excepciones especificas: `ExpiredException`, `SignatureInvalidException`, `BeforeValidException`
- Todas se deben capturar y traducir a `BadCredentialsException` generico (no revelar detalles)

### RFC 7807 para errores 401

Debe usar `Content-Type: application/problem+json`:
```json
{
  "type": "https://enbandeja.example.com/errors/authentication-required",
  "title": "Authentication Required",
  "status": 401,
  "detail": "Valid JWT token is required"
}
```

**CRITICO**: El detalle NO debe decir "token expirado" o "firma invalida" — es informacion util para atacantes.

### Previous Story Intelligence (Story 1.1)

- Proyecto Symfony 7.2 ya inicializado con todas las dependencias base
- `config/packages/security.yaml` existe con firewall basico (main: lazy: true)
- `src/Infrastructure/Security/` directorio ya creado (vacio)
- Domain puro: los archivos de Security van en Infrastructure
- Patron de naming: PascalCase + sufijo descriptivo (JwtUser, JwtAccessTokenHandler)

### Project Structure — Archivos a crear/modificar

```
src/Infrastructure/Security/
├── JwtUser.php                         (nuevo)
├── JwtAccessTokenHandler.php           (nuevo)
└── AuthenticationFailureListener.php   (nuevo)

config/packages/security.yaml           (modificar)
config/services.yaml                    (modificar)
.env                                    (modificar — agregar JWT_SECRET)

tests/Unit/Infrastructure/Security/
├── JwtAccessTokenHandlerTest.php       (nuevo)
└── JwtUserTest.php                     (nuevo)
```

### Testing

- Tests unitarios mockeando la decodificacion JWT
- NO tests funcionales con kernel real en esta story (se haran en stories 1.3-1.5 cuando existan endpoints)
- firebase/php-jwt se mockea indirectamente pasando tokens firmados con clave conocida en tests

### References

- [Source: architecture.md#AD-03 — JWT solo validacion]
- [Source: architecture.md#AD-07 — RFC 7807 para errores]
- [Source: prd.md#FR-SUB-05 — JWT obligatorio, 401 sin JWT valido]
- [Source: prd.md#NFR-09 — JWT en todas las operaciones]
- [Source: prd.md#NFR-10 — Cero tokens en logs]
- [Symfony Access Token Auth](https://symfony.com/doc/current/security/access_token.html)
- [firebase/php-jwt](https://github.com/firebase/php-jwt)

## Dev Agent Record

### Agent Model Used

### Completion Notes List

### File List
