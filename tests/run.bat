@echo off
color 0A
set PHP="E:\lab\www\xampp\php\php.exe"
%PHP% -f "%CD%\run.php" -- "%1" %2
echo.
exit
