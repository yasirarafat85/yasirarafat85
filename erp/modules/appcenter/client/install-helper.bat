@echo off
setlocal EnableDelayedExpansion
title App Center - Helper Setup

REM ================================================================
REM  App Center helper setup (Windows) - SIMPLE, single script
REM  ----------------------------------------------------------------
REM  Double-click this. It:
REM    1. makes a hidden folder in AppData (no admin needed)
REM    2. copies appdeploy.ps1 there
REM    3. writes your server URL into it
REM    4. registers the appdeploy:// protocol
REM  ----------------------------------------------------------------
REM  Keep install-helper.bat and appdeploy.ps1 in the SAME folder.
REM  (For standard-user PCs that need admin-free machine installs,
REM   use setup-elevation.ps1 instead - see SETUP.md.)
REM ================================================================

set "HELPERDIR=%LOCALAPPDATA%\AppDeploy"

echo ================================================
echo    App Center - Helper Setup
echo ================================================
echo.

if not exist "%~dp0appdeploy.ps1" (
  echo [ERROR] appdeploy.ps1 not found next to this file.
  echo         Put install-helper.bat and appdeploy.ps1 in the same folder.
  pause & exit /b 1
)

echo Server address (where erp runs).  Example: http://192.168.0.10/erp
echo.
set /p SERVERURL="Server URL: "
if "%SERVERURL%"=="" ( echo [ERROR] Server URL required. & pause & exit /b 1 )
if "%SERVERURL:~-1%"=="/" set SERVERURL=%SERVERURL:~0,-1%

echo.
echo [1/4] Folder: %HELPERDIR%
if not exist "%HELPERDIR%" mkdir "%HELPERDIR%"

echo [2/4] Copy script...
REM clear hidden/readonly first so copy /Y can overwrite an old hidden file
attrib -h -r "%HELPERDIR%\appdeploy.ps1" >nul 2>&1
copy /Y "%~dp0appdeploy.ps1" "%HELPERDIR%\appdeploy.ps1" >nul
if errorlevel 1 ( echo [ERROR] copy failed. Delete "%HELPERDIR%" and run again. & pause & exit /b 1 )

echo [3/4] Write server URL into the script...
powershell -NoProfile -Command "(Get-Content '%HELPERDIR%\appdeploy.ps1' -Raw) -replace 'http://YOUR-SERVER/erp','%SERVERURL%' | Set-Content -Encoding utf8 '%HELPERDIR%\appdeploy.ps1'"
attrib +h "%HELPERDIR%\appdeploy.ps1" >nul 2>&1
attrib +h "%HELPERDIR%" >nul 2>&1

echo [4/4] Register appdeploy:// protocol...
reg add "HKCU\Software\Classes\appdeploy" /ve /d "URL:App Center Deploy Protocol" /f >nul
reg add "HKCU\Software\Classes\appdeploy" /v "URL Protocol" /d "" /f >nul
reg add "HKCU\Software\Classes\appdeploy\shell\open\command" /ve /d "powershell.exe -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File \"%HELPERDIR%\appdeploy.ps1\" \"%%1\"" /f >nul

echo.
echo ================================================
echo    [OK] Done!
echo    Server : %SERVERURL%
echo    Now click Install/Update/Uninstall in the dashboard.
echo    A popup will tell you the result.
echo ================================================
echo.
pause
