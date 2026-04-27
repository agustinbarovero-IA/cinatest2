@echo off
:: ─────────────────────────────────────────────────
:: ejecutar_sync.bat
:: Llama al script de sincronización y guarda log
:: Programar en Task Scheduler de Windows cada 24hs
:: ─────────────────────────────────────────────────

SET PYTHON=C:\Python312\python.exe
SET SCRIPT=C:\scripts\sync_cintia.py
SET LOGDIR=C:\scripts\logs

:: Crear carpeta de logs si no existe
IF NOT EXIST "%LOGDIR%" mkdir "%LOGDIR%"

:: Ejecutar
echo [%DATE% %TIME%] Iniciando sync >> "%LOGDIR%\run.log"
%PYTHON% %SCRIPT% >> "%LOGDIR%\run_%DATE:~-4%%DATE:~3,2%%DATE:~0,2%.log" 2>&1

IF %ERRORLEVEL% EQU 0 (
    echo [%DATE% %TIME%] Sync OK >> "%LOGDIR%\run.log"
) ELSE (
    echo [%DATE% %TIME%] Sync FALLO con codigo %ERRORLEVEL% >> "%LOGDIR%\run.log"
)
