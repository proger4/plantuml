@echo off
setlocal EnableDelayedExpansion
title JetBrains Trial Reset - Windows (User Mode)

:: ═══════════════════════════════════════════════════════════════
:: JetBrains Trial Reset для Windows — чистый BAT, без PowerShell
:: Запускается от имени обычного пользователя (без прав админа)
:: ═══════════════════════════════════════════════════════════════

echo.
echo ════════════════════════════════════════════
echo   JetBrains Trial Reset — Windows Edition
echo   (без PowerShell, без прав администратора)
echo ════════════════════════════════════════════
echo.

:: Формат: Имя_папки:Имя_процесса
:: Папки могут содержать версию: PyCharm2024.1, IntelliJIdea2023.3 и т.д.
set "products=IntelliJIdea:idea WebStorm:webstorm DataGrip:datagrip PhpStorm:phpstorm CLion:clion PyCharm:pycharm GoLand:goland RubyMine:rubymine Rider:rider DataSpell:dataspell AppCode:appcode"

for %%A in (%products%) do (
    for /f "tokens=1,2 delims=:" %%B in ("%%A") do (
        set "folder=%%B"
        set "exe=%%C"

        echo [✦] Продукт: !folder!

        :: ── 1. Завершение процесса (работает для процессов текущего пользователя)
        echo     ├─ Завершение !exe!.exe...
        taskkill /F /IM !exe!.exe >nul 2>&1
        if errorlevel 1 (
            echo     │  [i] Не запущен
        ) else (
            echo     │  [✓] Завершён
        )

        :: ── 2. Удаление папок eval
        echo     ├─ Очистка eval-ключей...
        set "found=false"

        :: Проверяем Roaming и Local AppData
        for %%L in ("%APPDATA%" "%LOCALAPPDATA%") do (
            for /d %%D in (%%~L\JetBrains\!folder!*) do (
                if exist "%%D\eval\" (
                    rmdir /s /q "%%D\eval" >nul 2>&1
                    echo     │  [✓] Удалено: %%~nxD\eval
                    set "found=true"
                )
            )
        )
        if "!found!"=="false" echo     │  [i] eval-папки не найдены

        :: ── 3. Очистка other.xml от строк с evlsprt (чистый batch)
        echo     ├─ Очистка other.xml...
        set "cleaned=false"

        for %%L in ("%APPDATA%" "%LOCALAPPDATA%") do (
            for /d %%D in (%%~L\JetBrains\!folder!*) do (
                set "xml=%%D\options\other.xml"
                if exist "!xml!" (
                    call :clean_xml "!xml!"
                    if "!result!"=="1" (
                        echo     │  [✓] Очищено: %%~nxD\options\other.xml
                        set "cleaned=true"
                    )
                )
            )
        )
        if "!cleaned!"=="false" echo     │  [i] other.xml не найден

        echo.
    )
)

:: ── 4. Доп. очистка пользовательских prefs
echo [✦] Доп. очистка...
del /f /q "%APPDATA%\JetBrains\*.eval" >nul 2>&1
del /f /q "%LOCALAPPDATA%\JetBrains\*.eval" >nul 2>&1
echo     [✓] Временные файлы удалены

echo.
echo ════════════════════════════════════════════
echo [★] Готово! Перезапустите IDE.
echo ════════════════════════════════════════════
pause
goto :eof

:: ═════════════════════════════════════════════
:: Подпрограмма: очистка XML через findstr (чистый batch)
:: Возвращает result=1 если строки были удалены
:: ═════════════════════════════════════════════
:clean_xml
set "src=%~1"
set "tmp=%src%.tmp"
set "result=0"

:: Считаем строки до
for /f %%C in ('type "!src!" ^| find /c /v ""') do set "before=%%C"

:: Удаляем строки, содержащие evlsprt, сохраняем кодировку
findstr /v /i "evlsprt" "!src!" > "!tmp!" 2>nul

:: Считаем строки после
for /f %%C in ('type "!tmp!" ^| find /c /v ""') do set "after=%%C"

:: Если количество строк изменилось — заменяем файл
if !before! neq !after! (
    move /y "!tmp!" "!src!" >nul 2>&1
    set "result=1"
) else (
    del "!tmp!" >nul 2>&1
)
goto :eof