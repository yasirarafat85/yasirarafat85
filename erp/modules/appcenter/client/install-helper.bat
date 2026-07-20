@echo off
setlocal EnableDelayedExpansion
title App Center - Silent Install Helper Setup

REM ================================================================
REM  App Center helper auto-installer (Windows)
REM  ----------------------------------------------------------------
REM  ei bat double-click korle sob nije kore ney:
REM    1. C:\AppDeploy folder banay
REM    2. appdeploy.ps1 copy kore
REM    3. apnar server URL bosay
REM    4. appdeploy:// protocol register kore (admin lage na)
REM  ----------------------------------------------------------------
REM  ei file ta appdeploy.ps1 er PASHE rekhe double-click korun.
REM ================================================================

echo ================================================
echo    App Center - Silent Install Helper Setup
echo ================================================
echo.

REM --- appdeploy.ps1 pashe achhe kina check ---
if not exist "%~dp0appdeploy.ps1" (
  echo [ERROR] appdeploy.ps1 khuje pawa jayni.
  echo         ei bat file ta appdeploy.ps1 er same folder-e rakhun.
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

REM --- shesher slash thakle sorao ---
if "%SERVERURL:~-1%"=="/" set SERVERURL=%SERVERURL:~0,-1%

echo.
echo [1/4] Folder toiri kora hocche: C:\AppDeploy
if not exist "C:\AppDeploy" mkdir "C:\AppDeploy"

echo [2/4] Script copy kora hocche...
copy /Y "%~dp0appdeploy.ps1" "C:\AppDeploy\appdeploy.ps1" >nul

echo [3/4] Server URL bosano hocche: %SERVERURL%
powershell -NoProfile -Command "(Get-Content 'C:\AppDeploy\appdeploy.ps1' -Raw) -replace 'http://YOUR-SERVER/erp', '%SERVERURL%' | Set-Content 'C:\AppDeploy\appdeploy.ps1'"

echo [4/4] appdeploy:// protocol register kora hocche...
reg add "HKCU\Software\Classes\appdeploy" /ve /d "URL:App Center Deploy Protocol" /f >nul
reg add "HKCU\Software\Classes\appdeploy" /v "URL Protocol" /d "" /f >nul
reg add "HKCU\Software\Classes\appdeploy\shell\open\command" /ve /d "powershell.exe -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File \"C:\AppDeploy\appdeploy.ps1\" \"%%1\"" /f >nul

echo.
echo ================================================
echo    [OK] Helper setup COMPLETE!
echo ================================================
echo    Folder : C:\AppDeploy
echo    Server : %SERVERURL%
echo.
echo    Ekhon dashboard-er "Install" button-e click korle
echo    silent install hobe. :)
echo.
pause
