@echo off
set "SCRIPT_DIR=%~dp0"
set "VBS_PATH=%SCRIPT_DIR%LifeStore.vbs"
set "SHORTCUT_NAME=LifeStore POS.lnk"
set "DESKTOP_PATH=%USERPROFILE%\Desktop"

echo Creating Desktop Shortcut...

powershell -Command "$s=(New-Object -COM WScript.Shell).CreateShortcut('%DESKTOP_PATH%\%SHORTCUT_NAME%');$s.TargetPath='wscript.exe';$s.Arguments='\"%VBS_PATH%\"';$s.WorkingDirectory='%SCRIPT_DIR%';$s.Description='LifeStore POS System';$s.Save()"

echo Done! Shortcut created on your Desktop.
pause
