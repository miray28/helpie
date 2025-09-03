@echo off
SETLOCAL EnableDelayedExpansion

echo Checking PHP installation...
where php >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo Error: PHP not found in PATH
    echo Please ensure PHP is installed and added to system PATH
    echo Expected location: C:\wamp64\bin\php\php8.x\php.exe
    pause
    exit /b 1
)

echo Checking required PHP extensions...
php -m | findstr "sockets" >nul
if %ERRORLEVEL% NEQ 0 (
    echo Error: PHP sockets extension not enabled
    echo Please enable extension=sockets in your php.ini
    pause
    exit /b 1
)

echo Starting Chat WebSocket Server...
cd /d "%~dp0"
php -f start-server.php 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo.
    echo Server failed to start. See error messages above.
    echo Check functions.php line 89 for syntax errors.
    pause
    exit /b 1
)
pause