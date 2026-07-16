@echo off
cd /d "%~dp0"

echo.
echo ==========================================
echo   EMISORA ONLINE - Modo red local (LAN)
echo ==========================================
echo.
echo El servidor quedara accesible desde otros equipos
echo en la misma red WiFi / cable.
echo.

for /f "tokens=2 delims=:" %%i in ('ipconfig ^| findstr /c:"IPv4"') do (
    for /f "tokens=1" %%j in ("%%i") do (
        echo   http://%%j:8000
    )
)

echo.
echo   En este PC tambien: http://127.0.0.1:8000
echo.
echo   Desde el celular u otro PC abre una de las URLs de arriba.
echo   Si no conecta, permite el puerto 8000 en el firewall de Windows.
echo.
echo ==========================================
echo.

php artisan serve --host=0.0.0.0 --port=8000
