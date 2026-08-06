# Integracion Angular con Gobernanza Auth

Este documento describe el flujo vigente para que CheckMate Angular se autentique
contra Gobernanza. El flujo web usa redireccion normal del navegador y un codigo
temporal de un solo uso. No se usan popups ni `postMessage`.

## URLs

Gobernanza local por defecto:

```text
http://localhost:8001
```

Base API:

```text
http://localhost:8001/api/v1
```

Login web centralizado:

```text
http://localhost:8001/login?client_id=governance-web-local&returnUrl=http://localhost:4200/portal
```

Produccion:

```text
https://login.checkmate.com/login?client_id=governance-web&returnUrl=https://checkmate.com/portal
```

Si el proyecto corre localmente en otro puerto, por ejemplo `http://localhost:4300`,
Angular debe apuntar sus variables de entorno a ese host.

## Variables de entorno de Angular

```ts
governanceBaseUrl: 'http://localhost:8001',
governanceApiUrl: 'http://localhost:8001/api/v1',
governanceLoginUrl: 'http://localhost:8001/login',
governanceLogoutUrl: 'http://localhost:8001/governance/logout',
governanceClientId: 'governance-web-local',
checkmateWebUrl: 'http://localhost:4200',
checkmatePortalUrl: 'http://localhost:4200/portal',
checkmatePostLogoutRedirectUrl: 'http://localhost:4200',
checkmateApiUrl: 'http://localhost:8000/api/v1',
```

Estos valores no son secretos. `GOVERNANCE_WEB_CLIENT_SECRET` nunca debe enviarse al
frontend.

## Variables de entorno de Gobernanza

El seeder de clientes autorizados lee:

```env
GOVERNANCE_WEB_CLIENT_ID=governance-web-local
GOVERNANCE_WEB_CLIENT_SECRET=governance-web-secret
CHECKMATE_WEB_URL=http://localhost:4200
CHECKMATE_WEB_PORTAL_URL=http://localhost:4200/portal
CHECKMATE_WEB_CALLBACK_URL=http://localhost:4200/auth/callback
```

`CHECKMATE_WEB_PORTAL_URL` es el retorno normal del login centralizado. El callback se
mantiene solo para compatibilidad.

## Flujo web por redireccion

1. El usuario da clic en **Abrir portal** en CheckMate.
2. Angular navega a `governanceLoginUrl` con `client_id` y `returnUrl`.
3. Gobernanza valida que el cliente este activo y que el `returnUrl` pertenezca a
   `client_apps.allowed_redirect_uris`.
4. El usuario ingresa correo y contrasena en Gobernanza.
5. Gobernanza valida credenciales.
6. Gobernanza crea un codigo temporal ligado al cliente y al `returnUrl`.
7. Gobernanza redirige al navegador a `returnUrl?code=...`.
8. Angular canjea el `code` en `/api/v1/auth/exchange-code`.
9. Angular valida el token con CheckMate-API `GET /api/v1/me`, guarda sesion y manda al
   portal del rol.

## Canjear codigo temporal

```http
POST /api/v1/auth/exchange-code
Content-Type: application/json
Accept: application/json
```

Body:

```json
{
  "client_id": "governance-web-local",
  "return_url": "http://localhost:4200/portal",
  "code": "codigo-temporal",
  "device_name": "web-redirect"
}
```

Respuesta:

```json
{
  "message": "Login exitoso.",
  "data": {
    "token": "1|abc123...",
    "token_type": "Bearer",
    "user": {
      "id": 42,
      "name": "Carlos Lopez",
      "email": "carlos.lopez@example.edu",
      "role": "profesor"
    }
  }
}
```

El codigo expira en 2 minutos y se invalida al primer canje. Si el `client_id` o
`return_url` no coinciden con los valores usados al emitirlo, Gobernanza responde:

```json
{
  "message": "Codigo de autenticacion invalido o expirado.",
  "code": "AUTH09",
  "errors": []
}
```

## Verificar token / usuario actual

Este endpoint sirve para que CheckMate-API valide un Bearer token emitido por
Gobernanza y obtenga el usuario dueno del token.

```http
GET /api/v1/auth/me
Authorization: Bearer {token}
Accept: application/json
```

Respuesta:

```json
{
  "message": "Usuario autenticado.",
  "data": {
    "user": {
      "id": 42,
      "name": "Carlos Lopez",
      "email": "carlos.lopez@example.edu",
      "role": "alumno"
    }
  }
}
```

Si el token no existe, expiro o es invalido, Gobernanza responde `401`.

## Login directo por API

Este endpoint se mantiene para clientes no web, por ejemplo movil. Requiere secreto de
cliente y no debe usarse desde Angular.

```http
POST /api/v1/auth/login
Content-Type: application/json
X-Client-Id: governance-mobile-local
X-Client-Secret: governance-mobile-secret
```

Body:

```json
{
  "email": "carlos.lopez@example.edu",
  "password": "temporal123",
  "device_name": "android"
}
```

## Logout

Angular primero revoca el Bearer token:

```http
POST /api/v1/auth/logout
Authorization: Bearer {token}
```

Luego navega al logout central:

```text
http://localhost:8001/governance/logout?client_id=governance-web-local&returnUrl=http://localhost:4200
```

Gobernanza valida el `returnUrl`, invalida la sesion web si existe y redirige al origen
de CheckMate.

## Refresh token

```http
POST /api/v1/auth/refresh
Authorization: Bearer {token}
```

Respuesta:

```json
{
  "message": "Token renovado con exito.",
  "data": {
    "token": "2|xyz789...",
    "token_type": "Bearer"
  }
}
```

## Crear usuarios en Gobernanza desde la API principal

Este endpoint es para llamadas sistema-a-sistema. No debe usarse desde Angular directo.

```http
POST /api/v1/internal/users
Content-Type: application/json
X-Client-Id: governance-web-local
X-Client-Secret: governance-web-secret
```

Roles validos:

- `alumno`
- `profesor`
- `tutor_academico`
- `administrador`
- `director_carrera`

La API principal debe guardar `data.user.id` como `governance_user_id` en su entidad de
dominio.
