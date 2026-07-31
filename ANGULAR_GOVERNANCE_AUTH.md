# Integracion Angular con Gobernanza Auth

Este documento describe lo que debe consumir CheckMate Angular para autenticarse contra el servicio de Gobernanza.

## URLs

Gobernanza local:

```text
http://localhost:8001
```

Base API:

```text
http://localhost:8001/api/v1
```

Login web popup:

```text
http://localhost:8001/governance/auth?client_id=governance-web-local&redirect_uri=http://localhost:4200/auth/callback
```

Si Angular/API corre dentro de Docker y necesita llamar a Gobernanza local:

```text
http://host.docker.internal:8001
```

## Variables sugeridas en Angular

```env
GOVERNANCE_BASE_URL=http://localhost:8001
GOVERNANCE_CLIENT_ID=governance-web-local
GOVERNANCE_REDIRECT_URI=http://localhost:4200/auth/callback
```

Si el proyecto Angular/API corre en Docker:

```env
GOVERNANCE_BASE_URL=http://host.docker.internal:8001
GOVERNANCE_CLIENT_ID=governance-web-local
GOVERNANCE_REDIRECT_URI=http://localhost:4200/auth/callback
```

## Flujo web con ventana emergente

Angular no debe mostrar el formulario de correo/contrasena. El formulario vive en Gobernanza.

Flujo:

1. Usuario da clic en `Iniciar sesion` en Angular.
2. Angular abre una ventana emergente hacia Gobernanza.
3. El usuario ingresa correo y contrasena en Gobernanza.
4. Gobernanza valida credenciales.
5. Gobernanza genera un token Bearer.
6. Gobernanza envia el token a Angular con `window.opener.postMessage`.
7. Gobernanza cierra la ventana emergente.
8. Angular guarda token y usuario.

## Abrir popup desde Angular

```ts
const governanceBaseUrl = 'http://localhost:8001';
const clientId = 'governance-web-local';
const redirectUri = 'http://localhost:4200/auth/callback';

const authUrl = `${governanceBaseUrl}/governance/auth?client_id=${encodeURIComponent(clientId)}&redirect_uri=${encodeURIComponent(redirectUri)}`;

window.open(
  authUrl,
  'governance-auth',
  'width=520,height=720'
);
```

## Recibir token en Angular

Angular debe escuchar el mensaje enviado por Gobernanza.

```ts
window.addEventListener('message', (event) => {
  const allowedOrigin = 'http://localhost:8001';

  if (event.origin !== allowedOrigin) {
    return;
  }

  if (event.data?.type !== 'governance_auth') {
    return;
  }

  const { token, token_type, user } = event.data.data;

  localStorage.setItem('auth_token', token);
  localStorage.setItem('auth_token_type', token_type);
  localStorage.setItem('auth_user', JSON.stringify(user));

  // Redirigir al dashboard o actualizar estado global de sesion.
});
```

Payload recibido:

```json
{
  "type": "governance_auth",
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

## Proteccion del popup

El popup de Gobernanza requiere:

- `client_id` valido.
- `redirect_uri` autorizado para ese cliente.

Esta URL funciona:

```text
http://localhost:8001/governance/auth?client_id=governance-web-local&redirect_uri=http://localhost:4200/auth/callback
```

Esta URL se bloquea con `403`:

```text
http://localhost:8001/governance/auth?client_id=governance-web-local
```

Gobernanza tambien envia el `postMessage` solo al origin derivado del `redirect_uri`, no a `*`.

## Login directo por API

Este endpoint es para clientes que no usan popup, por ejemplo movil.

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

Respuesta exitosa:

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

Errores esperados:

```json
{
  "message": "Credenciales incorrectas.",
  "code": "AUTH01",
  "errors": []
}
```

```json
{
  "message": "Tu cuenta esta desactivada. Contacta al administrador.",
  "code": "AUTH03",
  "errors": []
}
```

```json
{
  "message": "Tu cuenta aun no ha sido verificada. Revisa tu correo.",
  "code": "AUTH04",
  "errors": []
}
```

## Logout

## Verificar token / usuario actual

Este endpoint sirve para que CheckMate-API valide un Bearer token emitido por Gobernanza y obtenga el usuario dueño del token.

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

Uso esperado desde CheckMate-API:

1. Recibe una peticion protegida con `Authorization: Bearer {token}`.
2. Llama a Gobernanza: `GET /api/v1/auth/me`.
3. Si Gobernanza responde `200`, usa `data.user.id` como `governance_user_id`.
4. Busca el usuario/entidad local enlazada con ese `governance_user_id`.
5. Si Gobernanza responde `401`, rechaza la peticion.

## Logout

```http
POST /api/v1/auth/logout
Authorization: Bearer {token}
```

Respuesta:

```json
{
  "message": "Sesion cerrada con exito.",
  "data": []
}
```

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

Body sin contrasena:

```json
{
  "name": "Carlos Lopez",
  "email": "carlos.lopez@example.edu",
  "role": "profesor",
  "active": true
}
```

Gobernanza genera una contrasena temporal:

```json
{
  "message": "Usuario creado en gobernanza.",
  "data": {
    "user": {
      "id": 42,
      "name": "Carlos Lopez",
      "email": "carlos.lopez@example.edu",
      "role": "profesor"
    },
    "temporary_password": "abc123..."
  }
}
```

Body con contrasena definida por la API principal:

```json
{
  "name": "Carlos Lopez",
  "email": "carlos.lopez@example.edu",
  "role": "profesor",
  "password": "Temporal123!",
  "password_confirmation": "Temporal123!",
  "active": true
}
```

Roles validos:

- `alumno`
- `profesor`
- `tutor_academico`
- `administrador`
- `director_carrera`

La API principal debe guardar el `data.user.id` como `governance_user_id` en su entidad de dominio.

## Contrasena temporal

Si la API principal no envia `password`, Gobernanza devuelve `temporary_password` una sola vez.

Uso esperado:

1. Admin crea usuario desde la app principal.
2. API principal llama a Gobernanza sin `password`.
3. Gobernanza genera `temporary_password`.
4. API principal entrega esa contrasena al usuario por el canal definido.
5. Usuario inicia sesion con correo y contrasena temporal.
6. Mas adelante se debe obligar al usuario a cambiarla.

La contrasena temporal no debe guardarse en texto plano en la app principal.

## Headers para consumir APIs protegidas

Despues de autenticarse:

```http
Authorization: Bearer {token}
Accept: application/json
```

Ejemplo Angular:

```ts
const token = localStorage.getItem('auth_token');

headers: {
  Authorization: `Bearer ${token}`,
  Accept: 'application/json'
}
```
