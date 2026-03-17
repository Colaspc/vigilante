#======================================================================
# PROYECTO ASIR: Agente De Monitorización Integral
#======================================================================

<#
 * Agente de monitorización diseñado para ejecutarse en equipos Windows.
 * Recoge métricas del sistema, tráfico de red, eventos de archivos
 * y capturas de pantalla, enviándolo todo a la API central del proyecto.
 * También sincroniza la configuración remota (bloqueos web y carpetas
 * vigiladas) desde el servidor en cada ciclo de envío.
 *
 * CORRECCIONES APLICADAS:
 *   v1.1 - Añadido retraso inicial de 30s para esperar sesión gráfica
 *   v1.1 - Filtro de ruido ampliado (in-addr.arpa, N/A)
 *   v1.1 - Eliminados falsos positivos de WEB_BLOCKED_ATTEMPT causados
 *          por la propia caché del archivo hosts (solo se reportan
 *          intentos de bloqueo con conexión TCP real, no solo DNS)
 *   v1.1 - Captura de pantalla con try/catch para resistir Session 0
 *   v1.1 - Límite de 3 capturas almacenadas (borra la más antigua)
#>


#----------------------------------------------------------------------
# -1. Seguridad TLS Y Certificados
#----------------------------------------------------------------------

# Forzar TLS 1.2 como protocolo mínimo de comunicación segura
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

<#
 * Se define una política que acepta cualquier certificado SSL,
 * necesario para comunicarse con el servidor del proyecto que usa
 * un certificado autofirmado. Solo debe usarse en entornos controlados.
#>
add-type @"
    using System.Net;
    using System.Security.Cryptography.X509Certificates;
    public class TrustAllCertsPolicy : ICertificatePolicy {
        public bool CheckValidationResult(
            ServicePoint srvPoint, X509Certificate certificate,
            WebRequest request, int certificateProblem) {
            return true;
        }
    }
"@
[Net.ServicePointManager]::CertificatePolicy = New-Object TrustAllCertsPolicy


#----------------------------------------------------------------------
# 0. Control De Instancias Únicas
#----------------------------------------------------------------------

<#
 * Antes de arrancar, se comprueba si ya hay otra instancia del vigilante
 * corriendo. Si la hay, el proceso actual termina para evitar duplicados
 * que generen datos redundantes o conflictos en el sistema de archivos.
#>
$MiID = $PID
$OtrosVigilantes = Get-CimInstance Win32_Process -Filter "Name = 'powershell.exe' AND CommandLine LIKE '%vigilante.ps1%'" | Where-Object { $_.ProcessId -ne $MiID }

if ($OtrosVigilantes) {
    Write-Host "Ya existe una instancia del vigilante ejecutándose. Cerrando..." -ForegroundColor Yellow
    exit
}


#----------------------------------------------------------------------
# 0.1 Retraso Inicial
#----------------------------------------------------------------------

<#
 * Se espera 30 segundos antes de arrancar para asegurar que el escritorio
 * del usuario está completamente cargado cuando la tarea del Programador
 * de Tareas lanza el agente al iniciar sesión.
 *
 * Sin este retraso, CopyFromScreen falla con "Invalid Handle" porque
 * la sesión gráfica todavía no está disponible en el momento en que
 * la tarea arranca, justo tras el login.
#>
Write-Host "Iniciando agente en 30 segundos..." -ForegroundColor Gray
Start-Sleep -Seconds 30


#======================================================================
# 1. CONFIGURACIÓN Y RUTAS
#======================================================================

#----------------------------------------------------------------------
# Rutas Base Del Sistema
#----------------------------------------------------------------------

# Ruta de instalación por defecto; si no existe, se usa el directorio del propio script
$BaseDir = "C:\Program Files\Vigilante"
if (!(Test-Path $BaseDir)) { $BaseDir = $PSScriptRoot }

# Archivos de configuración y datos locales
$TxtFile       = Join-Path $BaseDir "carpetas.txt"   # Lista de carpetas a vigilar
$JsonFolder    = Join-Path $BaseDir "Envios_JSON"     # Carpeta para JSONs de envío (reserva local)
$ScreensFolder = Join-Path $BaseDir "Capturas"        # Carpeta para capturas de pantalla
$ConfigFile    = Join-Path $BaseDir "config.json"     # Token y código del equipo registrado
$BloqueosFile  = Join-Path $BaseDir "bloqueos.txt"    # Lista de dominios bloqueados


#----------------------------------------------------------------------
# URLs De La API Y Autenticación
#----------------------------------------------------------------------

# Endpoints del servidor central del proyecto ASIR
$ApiRegisterUrl  = "https://100.78.44.14/api/register_pc.php"   # Registro inicial del equipo
$ApiSendDataUrl  = "https://100.78.44.14/api/send_data.php"     # Envío de eventos y métricas
$ApiGetConfigUrl = "https://100.78.44.14/api/get_config.php"    # Sincronización de configuración remota

# Clave de API compartida para autenticar todas las peticiones
$ApiKey = "9ad019bdd0a48db0fa102d391d73040d5361d05b4edc216f571e64f4bd8ef377"


#----------------------------------------------------------------------
# Intervalos De Ejecución (En Segundos)
#----------------------------------------------------------------------

# Tiempo entre cada envío de métricas y eventos al servidor
$IntervaloEnvio   = 30

# Tiempo entre cada lectura del tráfico de red (bucle principal)
$IntervaloRed     = 2

# Tiempo entre cada captura de pantalla automática
$IntervaloCaptura = 30


#----------------------------------------------------------------------
# Variables Globales Y Timestamps Iniciales
#----------------------------------------------------------------------

$Global:EventList = New-Object System.Collections.Generic.List[PSObject]
$UltimoEnvio   = [datetime]::Now
$UltimaCaptura = [datetime]::Now


#----------------------------------------------------------------------
# Inicialización De Directorios Y Archivos Por Defecto
#----------------------------------------------------------------------

# Crear carpetas de trabajo si no existen
@($JsonFolder, $ScreensFolder) | ForEach-Object {
    if (!(Test-Path $_)) { New-Item -ItemType Directory -Path $_ -Force | Out-Null }
}

# Crear carpetas.txt con rutas por defecto si no existe
if (!(Test-Path $TxtFile)) {
    @("$env:USERPROFILE\Desktop", "$env:USERPROFILE\Documents") | Out-File -FilePath $TxtFile -Encoding utf8
}

# Crear bloqueos.txt con dominios de ejemplo si no existe
if (!(Test-Path $BloqueosFile)) {
    @("facebook", "tiktok", "youtube") | Out-File -FilePath $BloqueosFile -Encoding utf8
}

# Cargar ensamblados necesarios para capturas de pantalla e imágenes
Add-Type -AssemblyName System.Windows.Forms, System.Drawing


#======================================================================
# 2. PREPARACIÓN DEL SISTEMA
#======================================================================

#----------------------------------------------------------------------
# Desactivar DNS Interno De Navegadores (Edge Y Chrome)
#----------------------------------------------------------------------

<#
 * Se desactiva el cliente DNS integrado de Edge y Chrome mediante
 * políticas de registro. Esto evita que los navegadores resuelvan
 * nombres por su cuenta (DNS-over-HTTPS), permitiendo que el agente
 * capture correctamente el tráfico DNS del sistema.
#>
Write-Host "Configurando políticas de red y DNS..." -ForegroundColor Gray
$Paths = @("HKLM:\SOFTWARE\Policies\Microsoft\Edge", "HKLM:\SOFTWARE\Policies\Google\Chrome")
foreach ($path in $Paths) {
    if (!(Test-Path $path)) { New-Item -Path $path -Force | Out-Null }
    Set-ItemProperty -Path $path -Name "BuiltInDnsClientEnabled" -Value 0 -ErrorAction SilentlyContinue
}

# Limpiar la caché DNS para partir de un estado limpio al iniciar
ipconfig /flushdns | Out-Null


#======================================================================
# 3. FUNCIONES AUXILIARES
#======================================================================

#----------------------------------------------------------------------
# Get-ImageMetadata — Leer Software De Creación De Una Imagen
#----------------------------------------------------------------------

<#
 * Extrae el campo "Software" de los metadatos EXIF de una imagen JPG o PNG.
 * Se usa para enriquecer los eventos FILE_Created con información sobre
 * qué programa generó la imagen detectada en las carpetas vigiladas.
 * Devuelve null si el archivo no es una imagen o no tiene ese metadato.
#>
function Get-ImageMetadata {
    param([string]$FilePath)
    try {
        if ($FilePath -match '\.(jpg|jpeg|png)$') {
            $Image    = [System.Drawing.Image]::FromFile($FilePath)
            $Prop     = $Image.PropertyItems | Where-Object { $_.Id -eq 0x0110 }
            $Software = if ($Prop) { [System.Text.Encoding]::ASCII.GetString($Prop.Value).Trim("`0") } else { "N/A" }
            $Image.Dispose()
            return $Software
        }
    } catch { return $null }
}


#----------------------------------------------------------------------
# Get-SystemMetrics — Recoger Métricas De Rendimiento Del Equipo
#----------------------------------------------------------------------

<#
 * Consulta el estado actual del sistema mediante CIM/WMI:
 *   - Porcentaje de uso de CPU
 *   - Porcentaje de RAM usada (calculado sobre total y libre)
 *   - Espacio libre en disco C: en GB
 *   - Top 5 procesos con ventana activa ordenados por consumo de CPU
 * Devuelve un PSCustomObject listo para serializar a JSON y enviar.
#>
function Get-SystemMetrics {
    try {
        $cpuporcentaje = (Get-CimInstance Win32_Processor).LoadPercentage
        $os            = Get-CimInstance Win32_OperatingSystem
        $totalRAM      = [Math]::Round($os.TotalVisibleMemorySize / 1MB, 2)
        $libreRAM      = [Math]::Round($os.FreePhysicalMemory / 1MB, 2)
        $ramporcentaje = [Math]::Round((($totalRAM - $libreRAM) / $totalRAM) * 100, 2)
        $discoC        = Get-CimInstance Win32_LogicalDisk -Filter "DeviceID='C:'"

        $TopApps = Get-Process | Where-Object { $_.MainWindowTitle } |
                   Sort-Object CPU -Descending | Select-Object -First 5 | ForEach-Object {
                       @{ Nombre = $_.ProcessName; CPU_Uso_Seg = [Math]::Round($_.CPU, 2); Inicio = $_.StartTime.ToString("HH:mm:ss") }
                   }

        return [PSCustomObject]@{
            Timestamp        = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
            Evento           = "METRICAS_SISTEMA"
            cpu_uso          = $cpuporcentaje
            ram_uso          = $ramporcentaje
            disco_libre_gb   = [Math]::Round($discoC.FreeSpace / 1GB, 2)
            Top_Aplicaciones = $TopApps
            Equipo           = $env:COMPUTERNAME
        }
    } catch { return $null }
}


#----------------------------------------------------------------------
# Sync-WebBlocks — Aplicar Bloqueos Web Al Archivo Hosts
#----------------------------------------------------------------------

<#
 * Lee la lista de dominios de bloqueos.txt y los escribe en el archivo
 * hosts de Windows como entradas 127.0.0.1, marcadas con "# Bloqueo_ASIR"
 * para identificarlas y poder eliminarlas en futuras sincronizaciones.
 * Solo modifica el archivo hosts si hay cambios reales, y recarga DNS.
#>
function Sync-WebBlocks {
    $RutaHosts = "C:\Windows\System32\drivers\etc\hosts"
    $Marca     = "# Bloqueo_ASIR"

    if (Test-Path $BloqueosFile) {
        $SitiosDeseados  = Get-Content $BloqueosFile | Where-Object { $_.Trim() -ne "" }
        $ContenidoLimpio = Get-Content $RutaHosts | Where-Object { $_ -notmatch $Marca }
        $NuevasLineas    = foreach ($Sitio in $SitiosDeseados) {
            "127.0.0.1    $($Sitio.Trim().ToLower())    $Marca"
        }
        $NuevoContenido = $ContenidoLimpio + $NuevasLineas

        if (Compare-Object (Get-Content $RutaHosts) $NuevoContenido) {
            try { $NuevoContenido | Set-Content $RutaHosts -Force; ipconfig /flushdns | Out-Null } catch {}
        }
    }
}


#----------------------------------------------------------------------
# Sync-RemoteConfig — Descargar Configuración Actualizada Del Servidor
#----------------------------------------------------------------------

<#
 * Contacta con la API para obtener la configuración asignada al equipo:
 *   - bloqueos: lista de dominios a bloquear (actualiza bloqueos.txt)
 *   - carpetas: lista de rutas a vigilar   (actualiza carpetas.txt)
 * Solo se ejecuta si el equipo ya está registrado (tiene ComputerToken).
 * Compara el contenido actual con el recibido antes de escribir,
 * evitando escrituras innecesarias si no hay cambios.
#>
function Sync-RemoteConfig {
    # Salir inmediatamente si el equipo aún no tiene token asignado
    if ([string]::IsNullOrEmpty($ComputerToken)) { return }

    $Headers = @{
        "Content-Type"     = "application/json"
        "X-API-Key"        = $ApiKey
        "X-Computer-Token" = $ComputerToken
    }

    try {
        # El endpoint solo necesita los headers para identificar el equipo; body vacío
        $Response = Invoke-RestMethod -Uri $ApiGetConfigUrl -Method POST -Headers $Headers `
                                      -Body "{}" -TimeoutSec 10 -ErrorAction Stop

        if ($Response.success) {

            #----------------------------------------------------------
            # Actualizar Bloqueos.txt Si El Servidor Devuelve Cambios
            #----------------------------------------------------------
            if ($Response.bloqueos -and $Response.bloqueos.Count -gt 0) {
                $nuevosBloqueos = $Response.bloqueos -join "`n"
                $actualBloqueos = if (Test-Path $BloqueosFile) { Get-Content $BloqueosFile -Raw } else { "" }
                if ($nuevosBloqueos.Trim() -ne $actualBloqueos.Trim()) {
                    $nuevosBloqueos | Set-Content $BloqueosFile -Encoding utf8 -Force
                    Write-Host "[CONFIG] bloqueos.txt actualizado desde el servidor." -ForegroundColor Cyan
                }
            }

            #----------------------------------------------------------
            # Actualizar Carpetas.txt Si El Servidor Devuelve Cambios
            #----------------------------------------------------------
            if ($Response.carpetas -and $Response.carpetas.Count -gt 0) {
                $nuevasCarpetas = $Response.carpetas -join "`n"
                $actualCarpetas = if (Test-Path $TxtFile) { Get-Content $TxtFile -Raw } else { "" }
                if ($nuevasCarpetas.Trim() -ne $actualCarpetas.Trim()) {
                    $nuevasCarpetas | Set-Content $TxtFile -Encoding utf8 -Force
                    Write-Host "[CONFIG] carpetas.txt actualizado desde el servidor." -ForegroundColor Cyan
                }
            }
        }
    } catch {
        Write-Warning "[CONFIG] No se pudo sincronizar configuración remota: $_"
    }
}


#======================================================================
# 4. REGISTRO DEL EQUIPO
#======================================================================

#----------------------------------------------------------------------
# Register-Computer — Registrar El Equipo En El Panel Central
#----------------------------------------------------------------------

<#
 * Envía el código de panel introducido por el usuario a la API.
 * Si el servidor lo valida, devuelve un api_token único para este equipo
 * que se usará como identificador en todos los envíos posteriores.
 * Devuelve null si el registro falla o el código no es válido.
#>
function Register-Computer {
    param ([string]$Code)
    $Headers = @{ "Content-Type" = "application/json"; "X-API-Key" = $ApiKey }
    $Body    = @{ computer_code = $Code } | ConvertTo-Json
    try {
        $Response = Invoke-RestMethod -Uri $ApiRegisterUrl -Method POST -Headers $Headers -Body $Body -ErrorAction Stop
        if ($Response.success) { return $Response.api_token } else { return $null }
    } catch { return $null }
}

# Si ya existe config.json, cargar el token guardado; si no, pedir código y registrar
if (Test-Path $ConfigFile) {
    $Config        = Get-Content $ConfigFile | ConvertFrom-Json
    $ComputerToken = $Config.ComputerToken
} else {
    $ComputerCode  = (Read-Host "Ingresa el código del panel").Trim().ToUpper()
    $ComputerToken = Register-Computer -Code $ComputerCode
    if (-not $ComputerToken) { Write-Error "Registro fallido"; exit }
    @{ ComputerCode = $ComputerCode; ComputerToken = $ComputerToken } | ConvertTo-Json | Out-File $ConfigFile -Encoding utf8
}


#======================================================================
# 5. MONITORIZACIÓN DE RED
#======================================================================

#----------------------------------------------------------------------
# Get-WebTrafficLog — Detectar Sitios Web Visitados Y Bloqueados
#----------------------------------------------------------------------

<#
 * Combina dos fuentes para detectar actividad web del equipo:
 *   - Caché DNS del sistema (consultas DNS recientes con estado OK)
 *   - Conexiones TCP activas en puertos 80 y 443 (HTTP/HTTPS)
 *
 * Para cada sitio nuevo detectado (no visto antes en la sesión):
 *   - Lo marca como WEB_BLOCKED_ATTEMPT si coincide con bloqueos.txt
 *     Y hay una conexión TCP real activa (no solo entrada en hosts)
 *   - Lo marca como WEB_DETECTED en caso contrario
 *
 * FILTROS APLICADOS:
 *   1. Dominios de infraestructura (Microsoft, Google, Akamai, Azure...)
 *   2. Entradas de resolución inversa (in-addr.arpa)
 *   3. Entradas sin nombre resuelto (N/A)
 *   4. Falsos positivos de bloqueo: dominios que aparecen en la caché
 *      DNS únicamente porque están en el archivo hosts apuntando a
 *      127.0.0.1. Estos se ignoran a menos que haya una conexión TCP
 *      real, lo que indicaría un intento de acceso genuino.
#>
function Get-WebTrafficLog {
    $ListaBloqueados = Get-Content $BloqueosFile -ErrorAction SilentlyContinue
    $Nuevos          = New-Object System.Collections.Generic.List[PSObject]

    # Fuente 1: caché DNS — dominios resueltos recientemente
    Get-DnsClientCache -ErrorAction SilentlyContinue | Where-Object { $_.Status -eq 0 } | ForEach-Object {
        $Nuevos.Add([PSCustomObject]@{ Sitio = $_.Name.ToLower(); Metodo = "DNS" })
    }

    # Fuente 2: conexiones TCP activas en puertos web (80 y 443)
    Get-NetTCPConnection -RemotePort 80, 443 -State Established -ErrorAction SilentlyContinue | ForEach-Object {
        try {
            $s = [System.Net.Dns]::GetHostEntry($_.RemoteAddress).HostName
            $Nuevos.Add([PSCustomObject]@{ Sitio = $s.ToLower(); Metodo = "TCP" })
        } catch {
            $Nuevos.Add([PSCustomObject]@{ Sitio = $_.RemoteAddress; Metodo = "TCP_IP" })
        }
    }

    <#
     * Filtro de ruido ampliado:
     *   - microsoft|windows|akamai|delivery|static|azure|google : infraestructura
     *   - 127\.0\.0\.1|localhost : loopback
     *   - in-addr\.arpa : resoluciones DNS inversas (PTR records del sistema)
     *   - ^n/a$ : entradas sin nombre resuelto que generan ruido
    #>
    $Filtro = "microsoft|windows|akamai|delivery|static|azure|google|127\.0\.0\.1|localhost|in-addr\.arpa|^n/a$"

    foreach ($Ev in ($Nuevos | Select-Object -Unique Sitio)) {

        # Saltar si el dominio coincide con el filtro de ruido
        if ($Ev.Sitio -match $Filtro) { continue }

        # Saltar si este sitio ya está en la lista de eventos de esta sesión
        if ($Global:EventList | Where-Object { $_.Sitio -eq $Ev.Sitio }) { continue }

        # Comprobar si el dominio coincide con alguno de los bloqueados
        $Blk = $false
        if ($ListaBloqueados) {
            foreach ($b in $ListaBloqueados) {
                if ($Ev.Sitio -like "*$($b.Trim())*") { $Blk = $true; break }
            }
        }

        <#
         * CORRECCIÓN DE FALSOS POSITIVOS:
         * Si el dominio está bloqueado y la detección viene solo de DNS,
         * se descarta. Esto evita reportar WEB_BLOCKED_ATTEMPT cuando
         * nadie ha intentado acceder realmente — el dominio aparece en
         * la caché DNS simplemente porque está en el archivo hosts con
         * la entrada 127.0.0.1, no porque el usuario lo haya visitado.
         * Solo se reporta el intento si hay una conexión TCP real (TCP
         * o TCP_IP), lo que confirma que el navegador intentó conectar.
        #>
        if ($Blk -and $Ev.Metodo -eq "DNS") { continue }

        $Global:EventList.Add([PSCustomObject]@{
            Timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
            Evento    = if ($Blk) { "WEB_BLOCKED_ATTEMPT" } else { "WEB_DETECTED" }
            Sitio     = $Ev.Sitio
            Metodo    = $Ev.Metodo
            Equipo    = $env:COMPUTERNAME
            Usuario   = $env:USERNAME
        })
    }
}


#======================================================================
# 6. ENVÍO DE DATOS AL SERVIDOR
#======================================================================

#----------------------------------------------------------------------
# Send-EventLogJson — Enviar La Cola De Eventos A La API
#----------------------------------------------------------------------

<#
 * Serializa la lista global de eventos a JSON y la envía al endpoint
 * de recepción de datos. Si la lista está vacía, envía un KEEP_ALIVE
 * para notificar al servidor que el agente sigue activo.
 * Tras el envío exitoso, vacía la lista para el siguiente ciclo.
#>
function Send-EventLogJson {
    param ([System.Collections.Generic.List[PSObject]]$Lista)

    $Payload = if ($Lista.Count -eq 0) {
        # Sin eventos reales: enviar heartbeat para confirmar que el agente está vivo
        @([PSCustomObject]@{ Timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"; Evento = "KEEP_ALIVE"; Equipo = $env:COMPUTERNAME })
    } else {
        $res = $Lista.ToArray(); $Lista.Clear(); $res
    }

    $Headers = @{
        "Content-Type"     = "application/json"
        "X-API-Key"        = $ApiKey
        "X-Computer-Token" = $ComputerToken
    }

    try {
        $Response = Invoke-RestMethod -Uri $ApiSendDataUrl -Method POST -Headers $Headers `
                                      -Body ($Payload | ConvertTo-Json -Depth 5) -TimeoutSec 30
    } catch {}
}


#======================================================================
# 7. VIGILANTE DE ARCHIVOS
#======================================================================

#----------------------------------------------------------------------
# FileAction — Callback Para Eventos Del FileSystemWatcher
#----------------------------------------------------------------------

# Scriptblock ejecutado cada vez que se crea, modifica o elimina un archivo vigilado
$FileAction = {
    $EvParam = $Event.SourceEventArgs
    $Path    = $EvParam.FullPath
    $CType   = $EvParam.ChangeType
    $Extra   = ""

    # Para archivos nuevos, intentar leer metadatos EXIF de software de creación
    if ($CType -eq "Created") {
        $Software = Get-ImageMetadata -FilePath $Path
        if ($Software) { $Extra = " | Software: $Software" }
    }

    $Global:EventList.Add([PSCustomObject]@{
        Timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
        Evento    = "FILE_${CType}"
        Ruta      = $Path
        Detalle   = $Extra
        Equipo    = $env:COMPUTERNAME
    })
    Write-Host "[ARCHIVO] ${CType}: $Path" -ForegroundColor Cyan
}


#----------------------------------------------------------------------
# Registro De Watchers Por Cada Carpeta En Carpetas.txt
#----------------------------------------------------------------------

<#
 * Se crea un FileSystemWatcher independiente para cada ruta listada
 * en carpetas.txt. Las propiedades se asignan una a una (no en bloque)
 * y EnableRaisingEvents se activa al final, tras haber asignado Path,
 * para evitar un bug conocido de PowerShell con el constructor -Property.
 * Se validan las rutas antes de crear el watcher y se informa si alguna
 * no existe o falla al registrarse.
#>
if (Test-Path $TxtFile) {
    Get-Content $TxtFile -Encoding utf8 | ForEach-Object {
        $P = $_.Trim()

        # Saltar líneas vacías o en blanco
        if ([string]::IsNullOrWhiteSpace($P)) { return }

        # Verificar que la ruta existe y es un directorio real
        if (-not (Test-Path $P -PathType Container)) {
            Write-Warning "[WATCHER] Ruta no encontrada, ignorando: '$P'"
            return
        }

        try {
            $W                       = New-Object System.IO.FileSystemWatcher
            $W.Path                  = $P
            $W.IncludeSubdirectories = $true
            $W.EnableRaisingEvents   = $true   # Activar siempre al final, después de asignar Path

            $id   = $P.GetHashCode()
            $leaf = Split-Path $P -Leaf

            Register-ObjectEvent $W "Created" -Action $FileAction -SourceIdentifier "Cre_${leaf}_${id}" | Out-Null
            Register-ObjectEvent $W "Changed" -Action $FileAction -SourceIdentifier "Cha_${leaf}_${id}" | Out-Null
            Register-ObjectEvent $W "Deleted" -Action $FileAction -SourceIdentifier "Del_${leaf}_${id}" | Out-Null

            Write-Host "[WATCHER] Vigilando: $P" -ForegroundColor Green
        } catch {
            Write-Warning "[WATCHER] Error al crear watcher para '$P': $_"
        }
    }
}


#======================================================================
# 8. BUCLE PRINCIPAL
#======================================================================

Write-Host "`n╔═══════════════════════════════════════════════════════════╗" -ForegroundColor Green
Write-Host "║   AGENTE ASIR ACTIVO - MONITORIZACIÓN INTEGRAL            ║" -ForegroundColor Green
Write-Host "╚═══════════════════════════════════════════════════════════╝`n" -ForegroundColor Green

<#
 * El bucle principal se ejecuta indefinidamente con un sleep de $IntervaloRed
 * segundos entre iteraciones. En cada vuelta:
 *   - Recoge tráfico de red (DNS + TCP)
 *   - Cada $IntervaloCaptura segundos: toma una captura de pantalla
 *   - Cada $IntervaloEnvio segundos:
 *       · Sincroniza configuración remota (bloqueos y carpetas)
 *       · Aplica los bloqueos web al archivo hosts
 *       · Recoge métricas del sistema
 *       · Envía todos los eventos acumulados al servidor
 * Al terminar (Ctrl+C o error), el bloque finally limpia los event subscribers.
#>
try {
    while ($true) {

        # Leer tráfico de red en cada iteración del bucle
        Get-WebTrafficLog
        $Ahora = [datetime]::Now

        #--------------------------------------------------------------
        # Captura De Pantalla Periódica
        #--------------------------------------------------------------
        if (($Ahora - $UltimaCaptura).TotalSeconds -ge $IntervaloCaptura) {
            try {
                <#
                 * Se envuelve en try/catch para que un fallo en la captura
                 * no mate el proceso. Puede fallar si se llama antes de que
                 * la sesión gráfica esté completamente disponible (aunque el
                 * retraso inicial de 30s debería evitar esto).
                #>
                $Screen  = [System.Windows.Forms.Screen]::PrimaryScreen
                $Bitmap  = New-Object System.Drawing.Bitmap($Screen.Bounds.Width, $Screen.Bounds.Height)
                $Graphic = [System.Drawing.Graphics]::FromImage($Bitmap)
                $Graphic.CopyFromScreen($Screen.Bounds.X, $Screen.Bounds.Y, 0, 0, $Bitmap.Size)
                $FilePath = Join-Path $ScreensFolder "Screenshot_$(Get-Date -Format 'yyyyMMdd_HHmmss').jpg"
                $Bitmap.Save($FilePath, [System.Drawing.Imaging.ImageFormat]::Jpeg)

                #------------------------------------------------------
                # Límite De Capturas Almacenadas
                #------------------------------------------------------
                <#
                 * Se mantiene un máximo de 3 capturas en disco para no
                 * consumir espacio innecesario. Tras guardar la nueva,
                 * se listan todas ordenadas por fecha y se borran las
                 * más antiguas si se supera el límite.
                #>
                $TodasCapturas = Get-ChildItem $ScreensFolder -Filter "*.jpg" | Sort-Object LastWriteTime
                if ($TodasCapturas.Count -gt 3) {
                    $TodasCapturas | Select-Object -First ($TodasCapturas.Count - 3) | ForEach-Object {
                        Remove-Item $_.FullName -Force
                    }
                }

                # Añadir el evento con la imagen en Base64 para envío al servidor
                $Global:EventList.Add([PSCustomObject]@{
                    Timestamp    = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
                    Evento       = "SCREENSHOT_TAKEN"
                    Archivo      = Split-Path $FilePath -Leaf
                    ImagenBase64 = [Convert]::ToBase64String([IO.File]::ReadAllBytes($FilePath))
                    Equipo       = $env:COMPUTERNAME
                })

                $Graphic.Dispose(); $Bitmap.Dispose()

            } catch {
                <#
                 * Si la captura falla (por ejemplo por Session 0 o porque
                 * el escritorio aún no está listo), se registra un evento
                 * de error informativo pero el agente sigue funcionando.
                #>
                $Global:EventList.Add([PSCustomObject]@{
                    Timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
                    Evento    = "SCREENSHOT_ERROR"
                    Detalle   = $_.Exception.Message
                    Equipo    = $env:COMPUTERNAME
                })
            }

            # Actualizar el timestamp siempre, aunque falle la captura,
            # para que el temporizador avance y no intente capturar en cada tick
            $UltimaCaptura = $Ahora
        }

        #--------------------------------------------------------------
        # Ciclo De Envío: Sincronizar, Bloquear Y Transmitir Eventos
        #--------------------------------------------------------------
        if (($Ahora - $UltimoEnvio).TotalSeconds -ge $IntervaloEnvio) {
            Sync-RemoteConfig                                          # Descargar config del servidor
            Sync-WebBlocks                                             # Aplicar bloqueos en hosts
            $Metricas = Get-SystemMetrics
            if ($null -ne $Metricas) { $Global:EventList.Insert(0, $Metricas) }  # Métricas al inicio del payload
            Send-EventLogJson -Lista $Global:EventList                 # Enviar todo al servidor
            $UltimoEnvio = $Ahora
        }

        Start-Sleep -Seconds $IntervaloRed
    }
} finally {
    # Limpiar todos los event subscribers registrados al salir
    Get-EventSubscriber | Unregister-Event
}
