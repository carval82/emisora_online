@echo off
cd /d "%~dp0"

echo.
echo ==========================================
echo   Crear EmisoraBroadcaster.exe
echo ==========================================
echo.

where python >nul 2>&1
if errorlevel 1 (
    echo Python no encontrado. Instala Python 3.10+ desde python.org
    pause
    exit /b 1
)

echo Instalando dependencias de compilacion...
python -m pip install -r requirements.txt pyinstaller -q

echo Compilando ejecutable (sin consola)...
python -m PyInstaller --noconfirm EmisoraBroadcaster.spec

if errorlevel 1 (
    echo Error al compilar.
    pause
    exit /b 1
)

if not exist "dist\config.json" (
    copy config.example.json dist\config.json >nul
)

echo.
echo ==========================================
echo   LISTO
echo ==========================================
echo.
echo   Ejecutable:
echo   %~dp0dist\EmisoraBroadcaster.exe
echo.
echo   Copia dist\EmisoraBroadcaster.exe a tu escritorio
echo   o al portatil emisor. FFmpeg debe estar instalado.
echo.
pause
