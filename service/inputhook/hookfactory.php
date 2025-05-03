<?php

use FFI\CData;

$fd2 = fopen("/tmp/hookfactory-" . $argv[1] . ".log", "a");
function logmsg2($str)
{
    global $fd2;
    fwrite($fd2, $str . "\n");
    // fflush($fd2);
    fsync($fd2);
}

logmsg2("hf");




print("Hello World\n");

/*$handle = $hf->newHook("int (*)(int, int)", "func1", function(callable $orig, int $arg1, int $arg2){
	$orig(0, 1);
	return 1234;
});*/
