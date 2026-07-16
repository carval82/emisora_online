# Emisora Oyente (PWA)

App instalable para escuchar la emisora con **servidor configurable**.

## URLs

| Entorno | URL |
|---------|-----|
| Local | http://127.0.0.1:8000/app/ |
| Railway | https://emisoraonline-production.up.railway.app/app/ |

## Uso

1. Abre `/app/` en el navegador o instala como PWA (Agregar a pantalla de inicio).
2. En **⚙ Servidor** pon la URL de tu emisora Laravel.
3. Por defecto: `https://emisoraonline-production.up.railway.app`

La app carga el reproductor completo (música, en vivo y mensajes) dentro de un marco seguro.

## Archivos

```
public/app/
  index.html    — Shell con configuración de servidor
  manifest.json — PWA
  sw.js         — Cache del shell
  icon.svg      — Icono
```

## Notas

- El reproductor real sigue en `/` del servidor configurado.
- Para cambiar de emisora, abre ⚙ y actualiza la URL.
