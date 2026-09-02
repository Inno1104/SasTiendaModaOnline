@echo off
title SaaS Tienda Moda - Servidor
color 0A
echo =========================================
echo   SaaS Tienda Moda - Iniciando servidor
echo =========================================
echo.
echo  URL: http://127.0.0.1:8081
echo  Presiona Ctrl+C para detener
echo.
cd /d "%~dp0"
php -S 127.0.0.1:8081 server.php
pause
