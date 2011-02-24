@echo off
REM cls
REM @del /f ext\php_dynacall.dll 2> NUL
REM bin2c php_dynacall_init.php php_dynacall_init.php.c dynacall_init_php > NUL
REM \dev\tcc\tiny_impdef.exe php5ts.dll > NUL
REM \dev\tcc\tcc.exe -Ic:\dev\php54-src\Zend -shared php_dynacall.c php_dynacall_init.php.c php5ts.def -lkernel32 -o ext\php_dynacall.dll
REM php test.php
cls 
call build.bat
php test.php