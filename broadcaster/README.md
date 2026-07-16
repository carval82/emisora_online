# Emisora Broadcaster (app local)

Programa Windows para capturar audio **directo de la tarjeta de sonido** (VB-Cable, micrófono, línea) y enviarlo a tu emisora Laravel en Railway o en local.

## Requisitos

1. **Python 3.10+** — [python.org](https://www.python.org/downloads/)
2. **FFmpeg** — [gyan.dev/ffmpeg/builds](https://www.gyan.dev/ffmpeg/builds/) (agrega `bin` al PATH)
3. Servidor Laravel corriendo (local o Railway)

## Instalación rápida

```bat
cd broadcaster
iniciar.bat
```

## Uso

1. Abre `iniciar.bat`
2. **URL del servidor**
   - Local: `http://192.168.40.58:8000`
   - Railway: `https://tu-app.up.railway.app`
3. Email y contraseña de admin → **Conectar / guardar**
4. Elige **entrada de audio** (ej. `CABLE Output (VB-Audio Virtual Cable)`)
5. **Iniciar transmisión**
6. Los oyentes abren la web de la emisora y pulsan Play en vivo

## Configurar Zara Radio + mezclador integrado

1. Instala [VB-Cable](https://vb-audio.com/Cable/)
2. Zara Radio → **Salida de emisión** = `CABLE Input`
3. Zara Radio → **Salida de cue** = tus altavoces (para preescuchar)
4. En Emisora Broadcaster:
   - **Música / Zara** → `CABLE Output (VB-Audio Virtual Cable)`
   - **Micrófono** → tu micrófono
   - Ajusta volúmenes con los sliders (sin Voicemeeter externo)
5. **Iniciar transmisión**

## Controles de volumen

| Control | Función |
|---------|---------|
| Volumen mic | Nivel de tu voz en la mezcla |
| Volumen música | Nivel de Zara / VB-Cable |
| Volumen general | Master de la señal enviada |
| Mic activo | Desactiva el mic sin cambiar dispositivo |

Los cambios de volumen se aplican en vivo mientras transmites.

## Configurar Zara Radio + VB-Cable (solo música)

1. Instala [VB-Cable](https://vb-audio.com/Cable/)
2. Zara Radio → salida de audio = **CABLE Input**
3. En esta app → música = **CABLE Output**

## API (para desarrolladores)

| Método | Ruta | Auth |
|--------|------|------|
| POST | `/api/broadcaster/login` | email + password |
| POST | `/api/broadcaster/start` | Bearer token |
| POST | `/api/broadcaster/stop` | Bearer token |
| POST | `/api/broadcaster/chunk` | Bearer token (multipart) |
| GET | `/api/broadcaster/status` | Bearer token |

Generar token manualmente:

```bash
php artisan broadcaster:token pcapacho24@gmail.com
```

## Archivos

- `app.py` — interfaz gráfica
- `audio_capture.py` — captura FFmpeg
- `api_client.py` — comunicación con Laravel
- `config.json` — URL, token, dispositivo (se crea al conectar)
