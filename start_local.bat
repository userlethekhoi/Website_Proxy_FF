@echo off
title ProxyFF - Local Server
echo ========================================
echo   ProxyFF - Local Test Server
echo ========================================
echo.

:: Check PHP
where php >nul 2>&1
if %ERRORLEVEL% neq 0 (
    echo [LOI] PHP chua duoc cai dat!
    echo        Tai PHP tu: https://windows.php.net/download
    echo        Giai nen vao C:\php va them vao PATH
    pause
    exit /b 1
)

:: Get local IP
for /f "tokens=2 delims=:" %%a in ('ipconfig ^| findstr "IPv4"') do set LOCAL_IP=%%a
set LOCAL_IP=%LOCAL_IP: =%

echo IP may tinh: %LOCAL_IP%
echo.

echo [1/2] Khoi dong Web Server...
start "ProxyFF-Web" php -S 0.0.0.0:8000
timeout /t 2 >nul
echo        Web: http://%LOCAL_IP%:8000

echo [2/2] Khoi dong MITM Proxy...
start "ProxyFF-MITM" python -m mitmproxy.tools.main mitmdump --listen-port 8080 --listen-host 0.0.0.0 -s proxy_modifier.py --ssl-insecure --set block_global=false --ignore-hosts ".*"
timeout /t 3 >nul
echo        Proxy: %LOCAL_IP%:8080

echo.
echo ========================================
echo   Cau hinh iPhone:
echo ========================================
echo   1. WiFi - Proxy Manual: %LOCAL_IP%:8080
echo   2. Safari - http://%LOCAL_IP%:8000
echo   3. Safari - http://mitm.it - Cai cert
echo   4. Settings - General - About - Trust
echo ========================================
echo.
echo   Nhan phim bat ky de DUNG tat ca...
pause >nul

taskkill /f /im php.exe >nul 2>&1
echo Da dung.
