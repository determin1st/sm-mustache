@echo off
set PHP="E:\lab\www\xampp\php\php.exe"
%PHP% -f "%CD%\run.php" -- "%1" %2
exit
