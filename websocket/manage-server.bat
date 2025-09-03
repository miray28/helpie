@echo off
if "%1"=="start" (
    echo Starting WebSocket server...
    start "Chat Server" php server.php
    echo Server started in new window.
) else if "%1"=="stop" (
    echo Stopping WebSocket server...
    for /f "tokens=5" %%a in ('netstat -ano ^| findstr :8080') do (
        taskkill /PID %%a /F
    )
    echo Server stopped.
) else (
    echo Usage: manage-server.bat [start^|stop]
)