@echo off
:: ======================================================================
:: DESINSTALADOR DEL AGENTE VIGILANTE - PROYECTO ASIR
:: ======================================================================
:: Este script detiene el servicio, lo elimina del registro de Windows
:: y borra todos los archivos del directorio de instalacion.
:: ATENCION: Esta accion es irreversible.
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
:: Intentamos pararlo antes de eliminarlo.
:: El "2>nul" suprime el error si ya estaba detenido.
net stop VigilanteASIR 2>nul


:: ----------------------------------------------------------------------
:: ELIMINAR EL SERVICIO DEL SISTEMA
:: ----------------------------------------------------------------------
:: NSSM elimina el servicio del registro de Windows completamente.
:: La palabra "confirm" evita que NSSM pida confirmacion interactiva.
nssm remove VigilanteASIR confirm


:: ----------------------------------------------------------------------
:: BORRAR LOS ARCHIVOS DE INSTALACION
:: ----------------------------------------------------------------------
:: Elimina la carpeta completa con todo su contenido:
::   /S = borrar subdirectorios y archivos recursivamente
::   /Q = modo silencioso, sin pedir confirmacion
rmdir /S /Q "C:\Program Files\Vigilante"


:: ----------------------------------------------------------------------
:: CONFIRMACION FINAL
:: ----------------------------------------------------------------------
echo.
echo =====================================================
echo  Desinstalacion completada.
echo  El servicio ha sido eliminado del sistema.
echo  Los archivos de C:\Program Files\Vigilante
echo  han sido borrados.
echo =====================================================
pause
