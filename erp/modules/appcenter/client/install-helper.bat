@echo off
setlocal EnableDelayedExpansion
title App Center - Silent Install Helper Setup

REM ================================================================
REM  App Center helper auto-installer (Windows) - SIMPLE MODE
REM  ----------------------------------------------------------------
REM  ei bat double-click korle sob nije kore ney (admin lage na):
REM    1. helper folder banay (hidden, AppData-te)
REM    2. appdeploy.ps1 + appdeploy-worker.ps1 copy kore
REM    3. config.json e server URL bosay
REM    4. appdeploy:// protocol register kore
REM  ----------------------------------------------------------------
REM  NOTE: ei simple mode admin/winget-user-scope er jonne.
REM  STANDARD USER pc-te admin chhara install/uninstall korte hole
REM  "setup-elevation.ps1" (admin) diye SYSTEM task setup korun.
REM  ----------------------------------------------------------------
REM  ei file ta appdeploy.ps1 + appdeploy-worker.ps1 er PASHE rekhe
REM  double-click korun.
REM ================================================================

REM --- helper folder (hidden, per-user, admin lage na) ---
set "HELPERDIR=%LOCALAPPDATA%\AppDeploy"

REM  Program Files-e rakhte chaile:  upore-r line comment kore
REM  nicher line ta on korun, ebong bat ta "Run as administrator" diye chalan:
REM  set "HELPERDIR=%ProgramFiles%\AppDeploy"

echo ================================================
echo    App Center - Silent Install Helper Setup
echo ================================================
echo.

REM --- dorkari script gulo pashe achhe kina check ---
if not exist "%~dp0appdeploy.ps1" (
  echo [ERROR] appdeploy.ps1 khuje pawa jayni.
  echo         ei bat ta appdeploy.ps1 er same folder-e rakhun.
  echo.
  pause & exit /b 1
)
if not exist "%~dp0appdeploy-worker.ps1" (
  echo [ERROR] appdeploy-worker.ps1 khuje pawa jayni.
  echo         appdeploy.ps1 + appdeploy-worker.ps1 duitai lagbe.
  echo.
  pause & exit /b 1
)

REM --- server URL nin ---
echo Apnar server-er thikana din (jekhane erp cholchhe).
echo Udahoron:  http://192.168.0.10/erp
echo.
set /p SERVERURL="Server URL: "
if "%SERVERURL%"=="" (
  echo.
  echo [ERROR] Server URL dorkar. Abar chalan.
  pause & exit /b 1
)
if "%SERVERURL:~-1%"=="/" set SERVERURL=%SERVERURL:~0,-1%

echo.
echo [1/5] Folder toiri: %HELPERDIR%
if not exist "%HELPERDIR%" mkdir "%HELPERDIR%"

echo [2/5] Script copy kora hocche...
REM  age-r run-e file gulo hidden thakle copy /Y "Access denied" dey,
REM  tai copy-r age hidden/readonly attribute soriye ni:
attrib -h -r "%HELPERDIR%\appdeploy.ps1"        >nul 2>&1
attrib -h -r "%HELPERDIR%\appdeploy-worker.ps1" >nul 2>&1
copy /Y "%~dp0appdeploy.ps1"        "%HELPERDIR%\appdeploy.ps1" >nul
if errorlevel 1 echo    [WARN] appdeploy.ps1 copy failed - AppDeploy folder delete kore abar cholan.
copy /Y "%~dp0appdeploy-worker.ps1" "%HELPERDIR%\appdeploy-worker.ps1" >nul
if errorlevel 1 echo    [WARN] appdeploy-worker.ps1 copy failed - AppDeploy folder delete kore abar cholan.
if exist "%~dp0appdeploy-test.ps1" (
  attrib -h -r "%HELPERDIR%\appdeploy-test.ps1" >nul 2>&1
  copy /Y "%~dp0appdeploy-test.ps1" "%HELPERDIR%\appdeploy-test.ps1" >nul
)

echo [3/5] Server URL config.json e bosano hocche: %SERVERURL%
powershell -NoProfile -Command "@{server='%SERVERURL%'} | ConvertTo-Json | Set-Content -Path '%HELPERDIR%\config.json' -Encoding utf8"

echo [4/5] Folder hidden kora hocche...
attrib +h "%HELPERDIR%" >nul 2>&1
attrib +h "%HELPERDIR%\appdeploy.ps1" >nul 2>&1
attrib +h "%HELPERDIR%\appdeploy-worker.ps1" >nul 2>&1

echo [5/5] appdeploy:// protocol register kora hocche...
reg add "HKCU\Software\Classes\appdeploy" /ve /d "URL:App Center Deploy Protocol" /f >nul
reg add "HKCU\Software\Classes\appdeploy" /v "URL Protocol" /d "" /f >nul
reg add "HKCU\Software\Classes\appdeploy\shell\open\command" /ve /d "powershell.exe -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File \"%HELPERDIR%\appdeploy.ps1\" \"%%1\"" /f >nul

echo.
echo ================================================
echo    [OK] Helper setup COMPLETE!
echo ================================================
echo    Folder : %HELPERDIR%   (hidden)
echo    Server : %SERVERURL%
echo.
echo    Ekhon dashboard-er "Install" button-e click korle
echo    silent install hobe. :)
echo.
pause
