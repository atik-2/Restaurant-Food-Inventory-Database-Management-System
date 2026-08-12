@echo off
title Restaurant DBMS -- keep this window open
cd /d "%~dp0"

echo ==================================================
echo    Restaurant ^& Food Inventory DBMS
echo ==================================================
echo.
echo    URL  :  http://localhost:8000
echo    LOGIN:  admin / admin123   or   rahim / 1234
echo    STOP :  close this window
echo.
echo    (Ei window ta khola rakho. Bondho korle server bondho.)
echo.

REM --- 2 sec por browser-e local website auto khule jabe ---
start "" cmd /c "timeout /t 2 >nul & start http://localhost:8000"

REM --- Python server run korbe, fallback to Node ---
where python >nul 2>nul && (
    python demo_server.py
) || (
    cd node
    if not exist "node_modules" call npm install
    where node >nul 2>nul && (node server.js) || ("C:\Program Files\nodejs\node.exe" server.js)
)

echo.
echo Server bondho hoye geche. Window bondho korte jekono key chapo.
pause >nul
