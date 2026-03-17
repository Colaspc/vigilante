# 🖥️ Vigilante — Sistema de Monitorización Remota

Sistema de monitorización remota para equipos Windows, desarrollado como Proyecto Final de Grado Superior (ASIR) por Francisco, Agustín y Nicolás — Curso 2024/2025.

Combina un agente ligero en PowerShell que corre en el cliente y un panel web centralizado accesible desde cualquier red mediante VPN mesh privada (Tailscale/WireGuard).

---

## ¿Qué hace?

- 📊 **Métricas en tiempo real** — CPU, RAM, disco y top 5 aplicaciones activas
- 📸 **Capturas de pantalla automáticas** cada 5 minutos
- 🌐 **Monitorización de navegación** — detección de dominios vía caché DNS y conexiones TCP
- 🚫 **Bloqueo de URLs** dinámico mediante archivo `hosts` de Windows
- 📁 **Vigilancia de archivos** — eventos de creación, modificación y borrado con `FileSystemWatcher`
- 🔒 **Panel web protegido** con autenticación de administrador y aislamiento de datos por usuario

---

## Arquitectura

```
[Equipo Windows]                    [Servidor Ubuntu]
  vigilante.ps1   ──── HTTPS ────▶  Apache + PHP 8
  (SYSTEM, oculto)   (Tailscale)    MariaDB
                                    Panel web (Vue.js 3)
```

---

## Instalación

```bash
# Clonar el repositorio
git clone https://github.com/Colaspc/bin-bash.git

# Importar la base de datos
sudo mysql -u root -p < monitor_system.sql
```

## Agente Windows

Ejecutar `instalador.exe` como administrador en el equipo a monitorizar. El instalador:

Al primer arranque, el agente solicitará el **código de 8 caracteres** generado desde el panel web para vincular el equipo.



---

## Base de datos

La base de datos `monitoring_system` contiene 6 tablas:

- `users` — Administradores del panel
- `computers` — Equipos monitorizados (vinculados por código de 8 chars)
- `computer_data` — Métricas de CPU, RAM y disco (JSON)
- `computer_screenshots` —  Capturas en binario JPG (máximo 3 por equipo)
- `pc_config` — Bloque de dominios web y monitrizacion de carpetas
- `computer_history` — Eventos de navegación y sistema de archivos
- `rate_limits` — Control de abuso de la API por IP

Los registros se eliminan automáticamente tras **30 días**.

---

## Stack tecnológico

| Capa          | Tecnología                      |
| ------------- | ------------------------------- |
| Agente        | PowerShell 5.1 + .NET Framework |
| Backend       | PHP 8 + PDO                     |
| Base de datos | MariaDB                         |
| Red privada   | Tailscale (WireGuard)           |
| Frontend      | Vue.js 3 + Bootstrap 5.3        |
| Servidor web  | Apache2 + mod_ssl               |

---

## Autores

Proyecto desarrollado por **Francisco**, **Agustín** y **Nicolás** — CFGS ASIR 2024/2025.
