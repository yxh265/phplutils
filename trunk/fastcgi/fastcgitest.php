<?php

require_once(__DIR__ . '/fastcgi.php');

class FastCGI {
	static public function accept() {
	}
}

class MyFastcgiClient extends FastcgiClient {
	public function handleRequest(FastcgiRequest $request) {
		$start = microtime(true);
		echo "HTTP/1.1 200 OK\r\n\r\n";
		echo "Hello World!\n";
		//for ($n = 0; $n < 1000000; $n++) ;
		$end = microtime(true);
		printf("%.6f\n", $end - $start);
	}
}

$socketServer = new SocketServer('MyFastcgiClient');
$socketServer->listen('127.0.0.1', 9001);
