@echo off
cls
bin2c dynacall_init.php dynacall_init.php.c dynacall_init_php
\dev\tcc\tcc.exe -shared dynacall.c dynacall_init.php.c php5ts.def -lkernel32 && php test.php