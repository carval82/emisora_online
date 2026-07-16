# 📻 Emisora Online

Emisora de radio online con Laravel. Sube canciones, crea playlists con auto-reproducción, recibe mensajes de oyentes y despliega en Railway.

## Características (Fase 1)

- 🎵 **Subir canciones** — MP3, WAV, OGG, M4A
- 📋 **Playlists** — Organiza la programación, orden o aleatorio
- ▶️ **Auto-reproducción** — Reproductor web que sigue sonando
- 💬 **Mensajes** — Los oyentes envían saludos en tiempo real
- 📱 **PWA** — Instalable como app en el móvil
- 🔴 **Modo EN VIVO** — Transmite con micrófono desde el navegador
- 🚀 **Railway ready** — Listo para desplegar

## Requisitos

- PHP 8.2+
- Composer
- SQLite (local) o PostgreSQL (producción)

## Instalación local

```bash
# Clonar e instalar
composer install
cp .env.example .env
php artisan key:generate

# Base de datos y datos iniciales
php artisan migrate
php artisan db:seed
php artisan storage:link

# Iniciar servidor
php artisan serve
```

Abre:
- **Emisora:** http://localhost:8000
- **Admin:** http://localhost:8000/admin

### Credenciales demo

| Campo | Valor |
|-------|-------|
| Email | admin@emisora.com |
| Password | password |

## Uso rápido

1. Entra al **panel admin** (`/admin`)
2. **Sube canciones** en la sección Canciones
3. **Crea una playlist** y agrega las canciones
4. **Activa la playlist** para que suene en la emisora
5. Comparte el link de la emisora (`/`) con tus oyentes

### Transmitir en vivo

1. Ve a **Admin → En vivo** (`/admin/live`)
2. Permite acceso al micrófono
3. Pulsa **Iniciar transmisión**
4. Los oyentes escuchan tu voz automáticamente (badge EN VIVO)
5. Pulsa **Detener** para volver a la música programada

**Consejo:** Usa auriculares para evitar eco.

## Despliegue en Railway

### 1. Subir a GitHub

```bash
git init
git add .
git commit -m "Emisora online — Laravel + broadcaster"
git branch -M main
git remote add origin https://github.com/TU_USUARIO/emisora_online.git
git push -u origin main
```

### 2. Crear proyecto en Railway

1. Entra en [Railway](https://railway.app) → **New Project** → **Deploy from GitHub repo**
2. Selecciona el repositorio `emisora_online`
3. Agrega un servicio **PostgreSQL** al mismo proyecto
4. En el servicio web, **Variables** → copia desde `.env.railway.example`:

| Variable | Valor |
|----------|-------|
| `APP_KEY` | Genera con `php artisan key:generate --show` |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | URL pública de Railway (ej. `https://emisora-production.up.railway.app`) |
| `DB_CONNECTION` | `pgsql` |
| `FILESYSTEM_DISK` | `public` |
| `LOG_CHANNEL` | `stderr` |
| `CACHE_STORE` | `database` |
| `SESSION_DRIVER` | `database` |
| `QUEUE_CONNECTION` | `database` |

`DATABASE_URL` la inyecta Railway al vincular PostgreSQL (no hace falta copiarla).

5. En el servicio web → **Settings** → **Networking** → **Generate Domain**
6. Railway despliega solo: build (`composer`, `npm run build`) + migrate + seed

### 3. App broadcaster (.exe)

La app Windows **no va en Railway**. Descarga/compila `broadcaster/dist/EmisoraBroadcaster.exe` y en **Servidor** pon la URL pública de Railway.

### Nota sobre archivos

En Railway el disco es efímero: canciones subidas y chunks en vivo se pierden al redeploy. Para producción estable conviene S3/R2 (fase futura).

## Estructura del proyecto

```
app/
├── Http/Controllers/
│   ├── Admin/          # Panel de administración
│   ├── Api/            # API para el reproductor
│   └── PlayerController.php
├── Models/
│   ├── Song.php
│   ├── Playlist.php
│   ├── Message.php
│   └── StationSetting.php
resources/views/
├── admin/              # Vistas del panel
└── player/             # Reproductor público
```

## Próximas fases

- [ ] Servidor de streaming Icecast (transmisión continua 24/7)
- [ ] WebSockets para mensajes en tiempo real
- [ ] Transmisión en vivo con micrófono (OBS)
- [ ] App móvil nativa (React Native)
- [ ] Storage en la nube (S3/R2) para archivos de audio

## Licencia

MIT
