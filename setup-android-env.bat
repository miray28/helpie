@echo off
echo Setting up Android environment variables...
echo.

REM Check if Android Studio is installed in default location
set "ANDROID_STUDIO_PATH=C:\Users\%USERNAME%\AppData\Local\Android\Sdk"
if exist "%ANDROID_STUDIO_PATH%" (
    echo Found Android SDK at: %ANDROID_STUDIO_PATH%
    set "ANDROID_HOME=%ANDROID_STUDIO_PATH%"
    goto set_variables
)

REM Check common Android SDK locations
set "ANDROID_SDK_PATH=C:\Android\Sdk"
if exist "%ANDROID_SDK_PATH%" (
    echo Found Android SDK at: %ANDROID_SDK_PATH%
    set "ANDROID_HOME=%ANDROID_SDK_PATH%"
    goto set_variables
)

echo.
echo Android SDK not found in common locations.
echo.
echo Please choose an option:
echo 1. Install Android Studio (recommended)
echo 2. Manually specify Android SDK path
echo.
set /p choice="Enter your choice (1 or 2): "

if "%choice%"=="1" (
    echo.
    echo Please download and install Android Studio from:
    echo https://developer.android.com/studio
    echo.
    echo After installation, run this script again.
    pause
    exit /b 1
) else if "%choice%"=="2" (
    echo.
    set /p ANDROID_HOME="Enter the full path to your Android SDK: "
    if not exist "%ANDROID_HOME%" (
        echo Error: The specified path does not exist.
        pause
        exit /b 1
    )
    goto set_variables
) else (
    echo Invalid choice.
    pause
    exit /b 1
)

:set_variables
echo.
echo Setting ANDROID_HOME to: %ANDROID_HOME%

REM Set environment variables for current session
setx ANDROID_HOME "%ANDROID_HOME%"
setx ANDROID_SDK_ROOT "%ANDROID_HOME%"

REM Add platform-tools to PATH
setx PATH "%PATH%;%ANDROID_HOME%\platform-tools"

echo.
echo Environment variables set successfully!
echo.
echo Please restart your command prompt or computer for changes to take effect.
echo.
echo After restarting, you can build your Android app by running:
echo cd android-app
echo build-app.bat
echo.
pause




