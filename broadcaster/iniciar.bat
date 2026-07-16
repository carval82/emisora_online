@echo off
cd /d "%~dp0"

echo.
echo ==========================================
echo   EMISORA BROADCASTER (app local)
echo ==========================================
echo.

where python >nul 2>&1
if errorlevel 1 (
    echo Python no encontrado. Instala Python 3.10+ desde python.org
    pause
    exit /b 1
)

where ffmpeg >nul 2>&1
if errorlevel 1 (
    echo.
    echo ADVERTENCIA: FFmpeg no esta en el PATH.
    echo Descargalo de https://www.gyan.dev/ffmpeg/builds/
    echo y agrega la carpeta bin al PATH de Windows.
    echo.
)

if not exist "config.json" (
    copy config.example.json config.json >nul
    echo Creado config.json — editalo o usa la app para conectar.
)

python -m pip install -r requirements.txt -q
python app.py
