@echo off
:: ======================================================================
:: PARAR EL AGENTE VIGILANTE - PROYECTO ASIR
:: ======================================================================
:: Este script detiene el servicio VigilanteASIR temporalmente.
:: El servicio NO se elimina, solo se detiene hasta el proximo reinicio
:: o hasta que se inicie manualmente.
:: Debe ejecutarse con permisos de Administrador.
:: ======================================================================


:: ----------------------------------------------------------------------
:: COMPROBACION DE PERMISOS DE ADMINISTRADOR
:: ----------------------------------------------------------------------
net session >nul 2>&1
if %errorLevel% neq 0 (
    echo [ERROR] Ejecuta este archivo como Administrador.
    pause & exit /b 1
)


:: ----------------------------------------------------------------------
:: DETENER EL SERVICIO
:: ----------------------------------------------------------------------
:: "net stop" envia una señal de parada al servicio.
:: Windows espera a que el proceso termine limpiamente.
net stop VigilanteASIR


:: ----------------------------------------------------------------------
:: CONFIRMACION
:: ----------------------------------------------------------------------
echo.
echo Servicio detenido correctamente.
echo Para volver a iniciarlo: net start VigilanteASIR
pause
