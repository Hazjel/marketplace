@echo off
echo Setting up ADB Reverse...
"%LOCALAPPDATA%\Android\Sdk\platform-tools\adb.exe" reverse tcp:8000 tcp:8000
if %errorlevel% neq 0 (
    echo Failed to run ADB reverse. Make sure Emulator is running.
    pause
    exit /b %errorlevel%
)
echo ADB Reverse success. Starting Flutter...
flutter run
