# Gobernanza Login

Proyecto Laravel 12 con Breeze, Blade, Tailwind/Vite y MySQL. El repositorio incluye una configuracion Docker para desarrollo local con PHP-FPM, Nginx, MySQL y Node/Vite.

## Requisitos

- Docker Desktop
- Docker Compose v2

No es necesario tener PHP, Composer, Node o MySQL instalados localmente para levantar el proyecto con Docker.

## Servicios Docker

| Servicio | Contenedor | Uso | Puerto |
| --- | --- | --- | --- |
| `nginx` | `gobernanza-login-nginx` | Servidor web | `8000:80` |
| `app` | `gobernanza-login-app` | PHP-FPM / Laravel / Composer / Artisan | `9000` interno |
| `mysql` | `gobernanza-login-mysql` | Base de datos MySQL | `3306:3306` |
| `node` | `gobernanza-login-node` | Vite dev server | `5173:5173` |

## Levantar el proyecto

Construye las imagenes y levanta los contenedores:

```bash
docker compose --env-file .env.docker up -d --build
```

Genera la llave de Laravel:

```bash
docker compose exec app php artisan key:generate
```

Ejecuta las migraciones y seeders:

```bash
docker compose exec app php artisan migrate --seed
```

Abre la aplicacion en:

```text
http://localhost:8000
```

Vite queda disponible en:

```text
http://localhost:5173
```

## Variables de entorno

Docker usa el archivo `.env.docker`. La configuracion principal es:

```env
APP_URL=http://localhost:8000
APP_PORT=8000

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=gobernanza_login
DB_USERNAME=gobernanza
DB_PASSWORD=secret
DB_ROOT_PASSWORD=rootsecret
DB_FORWARD_PORT=3306
```

Si el puerto `8000` o `3306` ya esta ocupado, cambia `APP_PORT` o `DB_FORWARD_PORT` en `.env.docker`.

## Comandos utiles

Ver contenedores:

```bash
docker compose ps
```

Ver logs:

```bash
docker compose logs -f
```

Ver logs de Laravel/PHP:

```bash
docker compose logs -f app
```

Entrar al contenedor de Laravel:

```bash
docker compose exec app bash
```

Ejecutar Artisan:

```bash
docker compose exec app php artisan migrate
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan route:list
docker compose exec app php artisan test
```

Ejecutar Composer:

```bash
docker compose exec app composer install
docker compose exec app composer update
```

Ejecutar NPM dentro del contenedor Node:

```bash
docker compose exec node npm install
docker compose exec node npm run build
```

Conectarse a MySQL:

```bash
docker compose exec mysql mysql -u gobernanza -p gobernanza_login
```

La password es:

```text
secret
```

## Apagar el proyecto

Detener los contenedores:

```bash
docker compose down
```

Detener y borrar la base de datos local:

```bash
docker compose down -v
```

Usa `down -v` solo si quieres eliminar el volumen `mysql-data` y reiniciar la base de datos desde cero.

## Archivos Docker

- `docker-compose.yml`: define los servicios de Laravel, Nginx, MySQL y Node.
- `.env.docker`: variables de entorno para Docker.
- `.dockerignore`: excluye dependencias y archivos locales del build.
- `docker/php/Dockerfile`: imagen PHP 8.3 con extensiones necesarias para Laravel.
- `docker/php/entrypoint.sh`: instala dependencias Composer si no existe `vendor/autoload.php` y prepara permisos de `storage`.
- `docker/nginx/default.conf`: virtual host de Nginx apuntando a `public`.

## Flujo recomendado

1. Inicia Docker Desktop.
2. Ejecuta `docker compose --env-file .env.docker up -d --build`.
3. Ejecuta `docker compose exec app php artisan key:generate` la primera vez.
4. Ejecuta `docker compose exec app php artisan migrate --seed`.
5. Trabaja desde `http://localhost:8000`.
