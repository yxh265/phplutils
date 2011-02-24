<?php

function phpste_iterator2($seq)
{
    if (is_array($seq) || (is_object($seq) && $seq instanceof Traversable)) {
        return $seq;
    } else {
        return array();
    }
}

$list = range(0, 9);
$list2 = new ArrayObject($list);

$count = 1000000;

$s1 = microtime(true);
for ($n = 0; $n < $count; $n++) {
	$a = phpste_iterator(array());
	$b = phpste_iterator(0);
	$c = phpste_iterator(NULL);
	$d = phpste_iterator($list2);
}
$s2 = microtime(true);
for ($n = 0; $n < $count; $n++) {
	$a = phpste_iterator2(array());
	$b = phpste_iterator2(0);
	$c = phpste_iterator2(NULL);
	$d = phpste_iterator2($list2);
}
$s3 = microtime(true);

printf("phpste_iterator: %.4f\n", $s2 - $s1);
printf("phpste_iterator2: %.4f\n", $s3 - $s2);

//var_dump();

//MessageBox(0, 'Hola', 'Prueba', 0);
//SDL_Init(0x20);
//SDL_SetVideoMode(640, 480, 0, 0);
