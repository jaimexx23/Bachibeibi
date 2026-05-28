# Despliegue y dependencias importantes

Este archivo lista las dependencias y pasos esenciales para migrar/desplegar este proyecto en un servidor de producción.

## Requisitos del sistema (recomendado)
- Sistema: Ubuntu 22.04 LTS (o similar)
- PHP >= 8.1 (8.2 recomendado)
- Composer
- Node.js >= 16 (Node 18 recomendado) + npm
- Base de datos: PostgreSQL o MySQL (Postgres recomendado)
- Redis (sessions, cache, colas, contadores)
- Nginx + PHP-FPM
- Supervisor o systemd para workers de colas
- Certbot (Let's Encrypt) para SSL

## Extensiones PHP necesarias
- `pdo`, `pdo_mysql` o `pdo_pgsql`
- `mbstring`
- `openssl`
- `tokenizer`
- `xml`
- `ctype`
- `json`
- `fileinfo`
- `gd` o `imagick` (para manipulación de imágenes/QR si aplica)
- `zip`
- `bcmath`

## Paquetes Composer importantes utilizados
- `bacon/bacon-qr-code` (generación de QR)
- (Opcional para Redis) `predis/predis` o la extensión `phpredis` si usas Redis

Comprueba `composer.json` para la lista completa de dependencias del proyecto.

## Comandos básicos de instalación en servidor (Ubuntu)

1) Actualizar e instalar paquetes base:

```bash
sudo apt update
sudo apt install -y git curl unzip software-properties-common build-essential
# PHP y extensiones (ajusta la versión si usas 8.1/8.2)
sudo apt install -y php8.2 php8.2-fpm php8.2-cli php8.2-xml php8.2-mbstring \
  php8.2-zip php8.2-gd php8.2-curl php8.2-pgsql php8.2-mysql php8.2-bcmath
# Redis
sudo apt install -y redis-server
```

2) Composer:

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

3) Node.js (ejemplo: Node 18):

```bash
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs
```

4) En la carpeta del proyecto:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build   # o `npm run prod` según tu setup
```

5) Variables de entorno y claves:

- No subas ni comites `.env` al repositorio. Crea el `.env` en el servidor con las credenciales.
- Genera `APP_KEY` si no existe:

```bash
php artisan key:generate
```

6) Migraciones y permisos:

```bash
php artisan migrate --force
php artisan storage:link
sudo chown -R www-data:www-data storage bootstrap/cache
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

7) Colas (opcional / recomendado para escrituras de asistencia):

Configura `QUEUE_CONNECTION=redis` en `.env` y ejecuta workers (con Supervisor o systemd):

```bash
php artisan queue:work --sleep=3 --tries=3
```

Se recomienda crear un `supervisor` config para mantener `queue:work` en producción.

## Redis y sesiones
En `.env` recomienda usar:

```
SESSION_DRIVER=redis
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
```

Usar Redis permite contadores atómicos y baja latencia para tracking de sesiones activas, en vez de tablas SQL con alta contención.

## Migración desde SQLite a Postgres/MySQL (resumen)
- Crear la base de datos objetivo en Postgres/MySQL.
- Actualizar `.env` con `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.
- Ejecutar `php artisan migrate --force` para crear tablas en la nueva BD.
- Para migrar datos existentes: exportar desde SQLite y convertir/importar a Postgres/MySQL. Para tablas sencillas puedes usar `sqlite3` para volcar CSVs y luego `psql` o `mysql` para importar.

Ejemplo (exportar `students` a CSV desde sqlite):

```bash
sqlite3 database/database.sqlite \
  "mode csv
.headers on
  .once students.csv
  select * from students;"
# luego importar a Postgres/MySQL según corresponda
```

Para una migración con integridad, recomiendo probar en staging, ejecutar scripts de transformación y validar registros.

## Notas de seguridad y operaciones
- No subir archivos con secretos (`.env`, claves privadas). Si subiste secretos, revócalos y rota contraseñas/keys.
- Habilita HTTPS (Certbot) y la política de CSP si manejas QR/escaneo.
- Monitoriza métricas (CPU, memoria, conexiones DB, longitud de colas) y prepara alertas.

## Recomendaciones operativas para alta concurrencia
- Cambiar de SQLite a Postgres/MySQL.
- Usar Redis para sesiones y contadores en tiempo real.
- Encolar las escrituras de asistencia y procesarlas con workers para suavizar picos.
- Escalar horizontalmente (varias réplicas de la app) detrás de un load balancer.
- Realizar pruebas de carga (`k6`, `locust`) y ajustar número de workers/instancias.

---

Si quieres, puedo generar un playbook más detallado (comandos paso a paso para Ubuntu), un archivo de configuración `supervisor` para `queue:work` y un `docker-compose.yml` opcional para un entorno de staging con Postgres + Redis. ¿Lo preparo ahora? 
