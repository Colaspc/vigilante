; ======================================================================
; VIGILANTE ASIR - Script de Inno Setup
; ======================================================================
; Genera un instalador .exe con interfaz grafica que:
;   1. Muestra pantalla de bienvenida
;   2. Pide el codigo de 8 digitos del panel y valida contra la API
;   3. Guarda config.json con el token
;   4. Registra una tarea en el Programador de Tareas de Windows
;      que lanza el agente cuando el usuario inicia sesion
;   5. Lanza el agente inmediatamente sin esperar al proximo login
;   NSSM se copia pero NO se usa como servicio (reserva)
; ======================================================================

[Setup]
AppName=Agente Vigilante ASIR
AppVersion=1.0
DefaultDirName=C:\Program Files\Vigilante
DisableDirPage=yes
DisableProgramGroupPage=yes
OutputBaseFilename=Setup_Vigilante
OutputDir=C:\Users\agust\Desktop\Estudios\WEB\Proyecto\Instalaciones\Output
Compression=lzma2/max
SolidCompression=yes
PrivilegesRequired=admin

; ======================================================================
[Languages]
Name: "spanish"; MessagesFile: "compiler:Languages\Spanish.isl"

; ======================================================================
[Files]
; vigilante.ps1 debe estar en UTF-8 con BOM para evitar corrupcion
Source: "vigilante.ps1"; DestDir: "{app}"; Flags: ignoreversion
; nssm.exe se copia como reserva pero no se usa para registrar servicio
Source: "nssm.exe";      DestDir: "{app}"; Flags: ignoreversion

; ======================================================================
[Code]

{ -------------------------------------------------------------------- }
{ VARIABLES GLOBALES                                                    }
{ -------------------------------------------------------------------- }
var
  PaginaCodigo  : TWizardPage;
  CampoCodigo   : TEdit;
  LabelEstado   : TLabel;
  CodigoValido  : Boolean;
  TokenObtenido : String;


{ -------------------------------------------------------------------- }
{ CREAR LA PAGINA DEL CODIGO DEL PANEL                                  }
{ -------------------------------------------------------------------- }
procedure InitializeWizard;
var
  LabelTitulo      : TLabel;
  LabelInstruccion : TLabel;
begin
  CodigoValido  := False;
  TokenObtenido := '';

  PaginaCodigo := CreateCustomPage(
    wpWelcome,
    'Registro del equipo',
    'Introduce el codigo de 8 caracteres generado en el panel ASIR para este equipo.'
  );

  LabelTitulo         := TLabel.Create(PaginaCodigo);
  LabelTitulo.Parent  := PaginaCodigo.Surface;
  LabelTitulo.Caption := 'Codigo del panel:';
  LabelTitulo.Left    := 0;
  LabelTitulo.Top     := 20;

  CampoCodigo           := TEdit.Create(PaginaCodigo);
  CampoCodigo.Parent    := PaginaCodigo.Surface;
  CampoCodigo.Left      := 0;
  CampoCodigo.Top       := 40;
  CampoCodigo.Width     := 220;
  CampoCodigo.CharCase  := ecUpperCase;
  CampoCodigo.MaxLength := 8;

  LabelInstruccion         := TLabel.Create(PaginaCodigo);
  LabelInstruccion.Parent  := PaginaCodigo.Surface;
  LabelInstruccion.Caption := 'Ejemplo: AB12CD34';
  LabelInstruccion.Left    := 0;
  LabelInstruccion.Top     := 70;

  LabelEstado            := TLabel.Create(PaginaCodigo);
  LabelEstado.Parent     := PaginaCodigo.Surface;
  LabelEstado.Left       := 0;
  LabelEstado.Top        := 100;
  LabelEstado.Width      := 440;
  LabelEstado.Caption    := '';
  LabelEstado.Font.Color := clRed;
end;


{ -------------------------------------------------------------------- }
{ VALIDAR EL CODIGO AL PULSAR SIGUIENTE                                 }
{ -------------------------------------------------------------------- }
function NextButtonClick(CurPageID: Integer): Boolean;
var
  Codigo     : String;
  ScriptPath : String;
  ResultPath : String;
  PSScript   : String;
  ResultCode : Integer;
  ResultLines: TArrayOfString;
  Token      : String;
begin
  Result := True;

  if CurPageID <> PaginaCodigo.ID then Exit;

  Codigo := Trim(CampoCodigo.Text);

  if Codigo = '' then
  begin
    LabelEstado.Font.Color := clRed;
    LabelEstado.Caption    := 'Debes introducir el codigo antes de continuar.';
    Result := False;
    Exit;
  end;

  if Length(Codigo) <> 8 then
  begin
    LabelEstado.Font.Color := clRed;
    LabelEstado.Caption    := 'El codigo debe tener exactamente 8 caracteres.';
    Result := False;
    Exit;
  end;

  LabelEstado.Font.Color := clBlue;
  LabelEstado.Caption    := 'Validando codigo con el servidor, espera...';

  ScriptPath := ExpandConstant('{tmp}\validar_codigo.ps1');
  ResultPath := ExpandConstant('{tmp}\validar_resultado.txt');

  PSScript :=
    '[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12' + #13#10 +
    'add-type @"' + #13#10 +
    '    using System.Net;' + #13#10 +
    '    using System.Security.Cryptography.X509Certificates;' + #13#10 +
    '    public class TrustAllCertsPolicy2 : ICertificatePolicy {' + #13#10 +
    '        public bool CheckValidationResult(' + #13#10 +
    '            ServicePoint srvPoint, X509Certificate certificate,' + #13#10 +
    '            WebRequest request, int certificateProblem) {' + #13#10 +
    '            return true;' + #13#10 +
    '        }' + #13#10 +
    '    }' + #13#10 +
    '"@' + #13#10 +
    '[Net.ServicePointManager]::CertificatePolicy = New-Object TrustAllCertsPolicy2' + #13#10 +
    'try {' + #13#10 +
    '    $response = Invoke-RestMethod `' + #13#10 +
    '        -Uri "https://100.78.44.14/api/register_pc.php" `' + #13#10 +
    '        -Method POST `' + #13#10 +
    '        -Headers @{ "Content-Type" = "application/json"; "X-API-Key" = "9ad019bdd0a48db0fa102d391d73040d5361d05b4edc216f571e64f4bd8ef377" } `' + #13#10 +
    '        -Body (''{"computer_code":"' + Codigo + '"}'') `' + #13#10 +
    '        -TimeoutSec 15 -ErrorAction Stop' + #13#10 +
    '    if ($response.success -and $response.api_token) {' + #13#10 +
    '        "OK" | Out-File "' + ResultPath + '" -Encoding utf8' + #13#10 +
    '        $response.api_token | Out-File "' + ResultPath + '" -Encoding utf8 -Append' + #13#10 +
    '    } else { "ERROR_CODIGO" | Out-File "' + ResultPath + '" -Encoding utf8 }' + #13#10 +
    '} catch { "ERROR_RED: $_" | Out-File "' + ResultPath + '" -Encoding utf8 }';

  SaveStringToFile(ScriptPath, PSScript, False);
  DeleteFile(ResultPath);

  Exec('powershell.exe',
    '-ExecutionPolicy Bypass -WindowStyle Hidden -NonInteractive -File "' + ScriptPath + '"',
    '', SW_HIDE, ewWaitUntilTerminated, ResultCode);

  if not LoadStringsFromFile(ResultPath, ResultLines) then
  begin
    LabelEstado.Font.Color := clRed;
    LabelEstado.Caption    := 'Error: no se pudo leer la respuesta del servidor.';
    Result := False;
    Exit;
  end;

  if (Length(ResultLines) >= 2) and (ResultLines[0] = 'OK') then
  begin
    Token         := ResultLines[1];
    CodigoValido  := True;
    TokenObtenido := Token;
    LabelEstado.Font.Color := $005A9E00;
    LabelEstado.Caption    := 'Codigo validado correctamente. Pulsa Siguiente para instalar.';
    Result := True;
  end
  else if (Length(ResultLines) >= 1) and (ResultLines[0] = 'ERROR_CODIGO') then
  begin
    LabelEstado.Font.Color := clRed;
    LabelEstado.Caption    := 'Codigo incorrecto. Verifica el codigo en el panel ASIR.';
    Result := False;
  end
  else
  begin
    LabelEstado.Font.Color := clRed;
    if Length(ResultLines) >= 1 then
      LabelEstado.Caption := 'Error de conexion: ' + ResultLines[0]
    else
      LabelEstado.Caption := 'No se pudo conectar con el servidor.';
    Result := False;
  end;

  DeleteFile(ScriptPath);
  DeleteFile(ResultPath);
end;


{ -------------------------------------------------------------------- }
{ TRAS INSTALAR LOS ARCHIVOS:                                           }
{   1. Crear config.json con el token                                   }
{   2. Registrar tarea en el Programador de Tareas (al iniciar sesion)  }
{   3. Lanzar el agente inmediatamente en segundo plano                 }
{ -------------------------------------------------------------------- }
procedure CurStepChanged(CurStep: TSetupStep);
var
  AppPath    : String;
  ConfigPath : String;
  ConfigJson : String;
  Usuario    : String;
  ResultCode : Integer;
begin
  if CurStep = ssPostInstall then
  begin
    AppPath := ExpandConstant('{app}');
    Usuario := GetUserNameString;

    { ---------------------------------------------------------------- }
    { 1. Crear config.json para que el agente no pida el codigo        }
    { ---------------------------------------------------------------- }
    ConfigPath := AppPath + '\config.json';
    ConfigJson :=
      '{' + #13#10 +
      '  "ComputerCode": "' + CampoCodigo.Text + '",' + #13#10 +
      '  "ComputerToken": "' + TokenObtenido + '"' + #13#10 +
      '}';
    SaveStringToFile(ConfigPath, ConfigJson, False);

    { ---------------------------------------------------------------- }
    { 2. Registrar tarea en el Programador de Tareas                   }
    { La tarea arranca el agente cada vez que el usuario inicia sesion  }
    { Corre en la sesion del usuario = acceso al escritorio y capturas  }
    { /F sobreescribe si ya existe, /RL HIGHEST para permisos elevados  }
    { ---------------------------------------------------------------- }
    Exec('schtasks.exe',
      '/Create /F /TN "WindowsTelemetryAgent" ' +
      '/TR "powershell.exe -ExecutionPolicy Bypass -WindowStyle Hidden -NonInteractive -File \"\"\"' + AppPath + '\vigilante.ps1\"\"\"" ' +
      '/SC ONLOGON ' +
      '/RU "' + Usuario + '" ' +
      '/RL HIGHEST',
      '', SW_HIDE, ewWaitUntilTerminated, ResultCode);

    { ---------------------------------------------------------------- }
    { 3. Lanzar el agente ahora mismo sin esperar al proximo login     }
    { Usa Start-Process para que corra desvinculado del instalador     }
    { -WindowStyle Hidden evita que aparezca ninguna ventana           }
    { ---------------------------------------------------------------- }
    Exec('powershell.exe',
      '-ExecutionPolicy Bypass -WindowStyle Hidden -NonInteractive -Command ' +
      '"Start-Process powershell.exe -ArgumentList ''-ExecutionPolicy Bypass -WindowStyle Hidden -NonInteractive -File """"' + AppPath + '\vigilante.ps1"""" '' -WindowStyle Hidden"',
      '', SW_HIDE, ewWaitUntilTerminated, ResultCode);
  end;
end;


{ -------------------------------------------------------------------- }
{ DESINSTALACION: ELIMINAR LA TAREA Y MATAR EL PROCESO                 }
{ -------------------------------------------------------------------- }
procedure CurUninstallStepChanged(CurUninstallStep: TUninstallStep);
var
  ResultCode : Integer;
begin
  if CurUninstallStep = usUninstall then
  begin
    { Eliminar la tarea del Programador de Tareas }
    Exec('schtasks.exe', '/Delete /F /TN "WindowsTelemetryAgent"',
      '', SW_HIDE, ewWaitUntilTerminated, ResultCode);

    { Matar el proceso de PowerShell que corre el agente si sigue activo }
    Exec('taskkill.exe', '/F /FI "WINDOWTITLE eq Vigilante*" /IM powershell.exe',
      '', SW_HIDE, ewWaitUntilTerminated, ResultCode);
  end;
end;
