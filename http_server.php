<?php

class SocketClient {
	protected $sock;
	private $_buffer = '';
	
	public function __construct($sock) {
		if ($sock === NULL) throw(new Exception("Invalid socket"));
		$this->sock = $sock;
		$this->opened = true;
		socket_set_nonblock($this->sock);
	}
	
	public function opened() {
		return ($this->sock != NULL);
	}
	
	public function send($data) {
		return socket_write($this->sock, $data);
	}
	
	public function close() {
		socket_shutdown($this->sock, 2);
		socket_close($this->sock);
		$this->sock = NULL;
	}

	public function tick() {
		do {
			$readed = socket_read($this->sock, 1024, PHP_BINARY_READ);
			//echo "\n\n[[[[[[" . socket_last_error($this->sock) . "]]]]]]]]]]]]\n";
			if ($readed === false) {
				break;
			}
			$this->_buffer .= $readed;
		} while (strlen($readed));

		if (strlen($this->_buffer) > 0) {
			$this->onData($this->_buffer);
			$this->_buffer = '';
		}
	}
	
	public function onData($data) {
	}
}

class SocketServer {
	protected $sock;
	protected $clients = array();
	protected $clientClass;

	public function __construct($port, $clientClass = 'SocketClient', $backlog = 128) {
		$this->sock = socket_create_listen($port);
		$this->clientClass = $clientClass;
		if ($this->sock === NULL) throw(new Exception("Invalid server socket"));
		socket_set_nonblock($this->sock);
	}
	
	public function getSocket($sock) {
		$clientClass = $this->clientClass;
		return new $clientClass($sock);
	}
	
	public function tick() {
		$error = $write = NULL;
		$read = array();
		$read[] = $this->sock;
	
		$count = socket_select($read, $write, $error, 0);
		foreach ($read as $read_sock) {
			if ($read_sock === $this->sock) {
				$client = $this->getSocket(socket_accept($this->sock));
				$this->clients[] = $client;
			}
		}

		// Client prunning
		$this->clients = array_filter($this->clients, function(SocketClient $client) { return $client->opened(); });

		foreach ($this->clients as $client) {
			$client->tick();
		}
	}
	
	public function loop() {
		while (true) {
			$this->tick();
			usleep(1000);
		}
	}
}

class HttpSocketClient extends SocketClient {
	public $raw_headers;
	public $httpData = '';

	public function onData($data) {
		$this->httpData .= $data;
		if (substr($this->httpData, -4) == "\r\n\r\n") {
			$this->onHeadersSended($this->httpData);
		}
	}
	
	public function generateContents() {
	}
	
	public function onHeadersSended($data) {
		$this->raw_headers = explode("\r\n", $data);
		ob_start();
		{
			$this->processHeaders();
			eval('$GLOBALS["HttpSocketClient"]->generateContents();');
		}
		$contents = ob_get_clean();
		
		$this->send("HTTP/1.1 200 OK\r\n");
		$this->send("Content-Type: text/html\r\n");
		$this->send("Connection: close\r\n");
		$this->send("\r\n");
		$this->send($contents);
		$this->close();
	}
	
	public function processHeaders() {
		$_SERVER = array();
		$raw_header = $this->raw_headers[0];
		preg_match('@^(GET|POST) (.*) HTTP/\d+\\.\\d+$@', $raw_header, $matches);
		$_SERVER['REQUEST_URI'] = $matches[2];
		$_SERVER['HTTP_METHOD'] = $matches[1];
		foreach (array_slice($this->raw_headers, 1) as $raw_header) {
			@list($type, $value) = explode(':', $raw_header, 2);
			$type = strtolower(trim($type));
			switch ($type) {
				case 'host': $_SERVER['HTTP_HOST'] = $value; break;
			}
		}
		$GLOBALS['HttpSocketClient'] = $this;
	}	
}

class AppHttpSocketClient extends HttpSocketClient {
	public function generateContents() {
		echo '<pre>';
		print_r($_SERVER);
		print_r($this->raw_headers);
	}
}


$socketServer = new SocketServer(80, 'AppHttpSocketClient');
$socketServer->loop();
