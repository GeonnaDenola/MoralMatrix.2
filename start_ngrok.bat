@echo off
:: Start ngrok hidden with reserved hostname

echo Set WshShell = CreateObject("WScript.Shell") > temp.vbs
echo WshShell.Run "C:\ngrok\ngrok.exe http --hostname=jani-sistroid-zaire.ngrok-free.dev 80", 0, False >> temp.vbs
cscript //nologo temp.vbs
del temp.vbs

echo Ngrok started at https://jani-sistroid-zaire.ngrok-free.dev/MoralMatrix
pause
