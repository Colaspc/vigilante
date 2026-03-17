
```powershell
Stop-ScheduledTask -TaskName "Vigilante_ASIR"
Start-Sleep -Seconds 3
PowerShell -NoProfile -ExecutionPolicy Bypass -File "C:\Program Files\Vigilante\vigilante.ps1"
```
# Agente Vigilante ASIR

Agente de monitorización diseñado para ejecutarse en equipos Windows como parte del Proyecto ASIR. Recoge métricas del sistema, tráfico de red, eventos de archivos y capturas de pantalla periódicas, enviándolo todo a la API central del proyecto.

---

## Contenido del repositorio

```
├── vigilante.ps1       # Script principal del agente
├── vigilante.iss       # Script de Inno Setup para generar el instalador
├── nssm.exe            # NSSM v2.24 (incluido como herramienta de reserva)
├── instalar.bat        # [RESERVA] Instalación manual via NSSM (enfoque anterior)
├── parar.bat           # [RESERVA] Parar el servicio NSSM manualmente
└── desinstalar.bat     # [RESERVA] Desinstalación via NSSM (enfoque anterior)
```

> **Nota sobre los archivos `.bat`:** Son utilidades del enfoque inicial basado en servicios NSSM, conservadas como referencia histórica del desarrollo. El método de instalación oficial es el instalador `.exe` generado con Inno Setup. Los `.bat` **no son compatibles** con la instalación actual basada en el Programador de Tareas — si se ejecutan en un equipo instalado con el `.exe`, fallarán al intentar gestionar un servicio NSSM que no existe. Para desinstalar correctamente usar siempre `unins000.exe` o el método manual indicado en la sección Desinstalación.

---

## Instalación

### Requisitos previos

- **Windows 10 / 11** (64 bits)
- **PowerShell 5.1+** (incluido por defecto en Windows 10/11)
- **Inno Setup 6.3+** — para compilar el instalador `.exe`
  - Descargar desde: https://jrsoftware.org/isdl.php
  - ⚠️ No usar la versión 7.0.0-preview — tiene un bug con rutas de instalación
- **NSSM** — incluido en el repo como `nssm.exe`. También se puede instalar con:
  ```powershell
  winget install nssm
  ```

---

### Paso 1 — Preparar `vigilante.ps1` en UTF-8 con BOM

Este paso es obligatorio. El script contiene caracteres especiales del español que Inno Setup corrompe si el archivo no tiene BOM. Sin este paso el agente fallará al ejecutarse con el error `TerminatorExpectedAtEndOfString`.

```powershell
$contenido = Get-Content "vigilante.ps1" -Raw -Encoding UTF8
$utf8bom = New-Object System.Text.UTF8Encoding $true
[System.IO.File]::WriteAllText("vigilante.ps1", $contenido, $utf8bom)
```

> Verificación: abrir en Notepad++ y comprobar que la esquina inferior derecha muestra `UTF-8-BOM`.

---

### Paso 2 — Compilar el instalador

1. Abrir **Inno Setup**
2. Cargar `vigilante.iss`
3. Ajustar la ruta `OutputDir` en la sección `[Setup]` si es necesario
4. Pulsar **F9** para compilar
5. El archivo `Setup_Vigilante.exe` aparece en la carpeta `Output\`

---

### Paso 3 — Instalar en el equipo destino

1. Copiar `Setup_Vigilante.exe` al equipo destino
2. Ejecutar como **Administrador**
3. Introducir el **código de 8 caracteres** generado en el panel ASIR para ese equipo
4. El instalador valida el código contra la API, copia los archivos y registra la tarea automáticamente

El agente arranca inmediatamente tras la instalación y en cada inicio de sesión posterior.

---

### Qué hace el instalador

- Copia `vigilante.ps1` y `nssm.exe` a `C:\Program Files\Vigilante\`
- Crea `config.json` con el token del equipo registrado
- Registra una tarea en el **Programador de Tareas de Windows** (`WindowsTelemetryAgent`) que lanza el agente al iniciar sesión del usuario
- Lanza el agente inmediatamente sin esperar al próximo reinicio

> **Por qué el Programador de Tareas y no un servicio de Windows:**
> Se intentó inicialmente instalar el agente como servicio usando NSSM, pero Windows aplica el mecanismo de **Session 0 Isolation** que impide que los servicios accedan al escritorio gráfico del usuario. Esto hacía que `CopyFromScreen` fallara con el error `Invalid Handle`. El Programador de Tareas con trigger `ONLOGON` lanza el proceso en la sesión del usuario, solucionando el problema.

---

### Archivos creados en el equipo destino

```
C:\Program Files\Vigilante\
├── vigilante.ps1       # El agente
├── nssm.exe            # Reserva
├── config.json         # Token y código del equipo
├── carpetas.txt        # Rutas vigiladas
├── bloqueos.txt        # Dominios bloqueados
├── Envios_JSON\        # JSONs de reserva local
├── Capturas\           # Capturas de pantalla (máximo 3)
└── unins000.exe        # Desinstalador
```

---

### Verificación post-instalación

```powershell
# Estado de la tarea
Get-ScheduledTask -TaskName "WindowsTelemetryAgent" | Select-Object TaskName, State

# Proceso activo
Get-Process powershell | Where-Object {$_.CommandLine -like "*vigilante*"}

# Capturas recientes
Get-ChildItem "C:\Program Files\Vigilante\Capturas" | Sort-Object LastWriteTime -Descending | Select-Object -First 5
```

---

### Desinstalación

Desde **Agregar o quitar programas** buscar `Agente Vigilante ASIR` y desinstalar.

O manualmente desde PowerShell como Administrador:

```powershell
schtasks /Delete /F /TN "WindowsTelemetryAgent"
taskkill /F /IM powershell.exe /FI "WINDOWTITLE eq Vigilante*"
Remove-Item -Recurse -Force "C:\Program Files\Vigilante"
```

---

## Notas técnicas

| Aspecto | Detalle |
|---|---|
| Intervalo de envío | 30 segundos |
| Intervalo de captura | 30 segundos |
| Máximo de capturas almacenadas | 3 (se borra la más antigua automáticamente) |
| Protocolo de comunicación | HTTPS con TLS 1.2 |
| Certificado del servidor | Autofirmado (aceptado explícitamente en el script) |
| Nombre de la tarea | `WindowsTelemetryAgent` |
| Ruta de instalación | `C:\Program Files\Vigilante\` |
