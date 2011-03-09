<?php

class SocketClient {
	static protected $lastId = 0;
	protected $sock;
	protected $id;
	private $_buffer = '';
	
	public function __construct($sock, $port = NULL) {
		if (is_string($sock)) {
			$sock = socket_create($sock, AF_INET, SOCK_STREAM);
			socket_connect($sock, $sock, $port);
		}
		if ($sock === NULL) throw(new Exception("Invalid socket"));
		$this->id = self::$lastId++;
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
			$readed = @socket_read($this->sock, 1024, PHP_BINARY_READ);
			if (socket_last_error($this->sock) == 10054) {
				echo "Connection interrupted\n";
				$this->sock = NULL;
				$readed = false;
			}
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
	
	public function __toString() {
		return (string)$this->id;
	}
}

class SocketServer {
	protected $sock;
	protected $clients = array();
	protected $clientClass;

	public function __construct($port, $clientClass = 'SocketClient', $backlog = 128) {
		echo "Listening at port '" . $port . "'...";
		$this->sock = socket_create_listen($port);
		echo "Ok\n";
		$this->clientClass = $clientClass;
		if ($this->sock === NULL) throw(new Exception("Invalid server socket"));
		socket_set_nonblock($this->sock);
	}
	
	public function getSocket($sock) {
		$clientClass = $this->clientClass;
		$client = new $clientClass($sock);
		echo "Connection opened {$this->clientClass}(" . $client . ")\n";
		return $client;
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
		$this->clients = array_filter($this->clients, function(SocketClient $client) {
			$opened = $client->opened();
			if (!$opened) {
				echo "Connection closed (" . $client . ")\n";
			}
			return $opened;
		});

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
	public $httpState = 0;
	public $raw_headers;
	public $recvContentLength = 0;
	public $httpData = '';
	
	public function onInit() {
		$_SERVER = array();
		$_GET = array();
		$_POST = array();
		$_REQUEST = array();
		$_COOKIE = array();
	}

	public function onData($data) {
		echo "{$data}\n";
		$this->httpData .= $data;

		// Reading headers.
		if ($this->httpState == 0) {
			if (strpos($this->httpData, "\r\n\r\n") !== false) {
				list($raw_headers, $this->httpData) = explode("\r\n\r\n", $this->httpData);
				$this->recvContentLength = 0;
				$this->onHeadersSended($raw_headers);
				$this->httpState = 1;
			}
		}

		// Reading post if available
		if ($this->httpState == 1) {
			if (strlen($this->httpData) >= $this->recvContentLength) {
				$_SERVER['HTTP_RAW_POST_DATA'] = substr($this->httpData, 0, $this->recvContentLength);
				parse_str($_SERVER['HTTP_RAW_POST_DATA'], $_POST);
				
				// variables_order = GPCS
				$_REQUEST = $_GET + $_POST + $_COOKIE + $_SERVER;
				
				$this->httpData = substr($this->httpData, $this->recvContentLength);
				$this->sendReply();
				$this->httpState = 0;
			}
		}
	}
	
	public function handleHttpRequest() {
		ob_start();
		{
			$this->outputHttpRequest();
		}
		return ob_get_clean();
	}
	
	public function outputHttpRequest() {
	}
	
	public function sendReply() {
		echo "REQUEST: {$_SERVER['REQUEST_URI']}\n";
		$contents = $this->handleHttpRequest();
		echo "  CONTENTS: {$contents}\n";
		
		$this->send("HTTP/1.1 200 OK\r\n");
		$this->send("Content-Length: " . strlen($contents) . "\r\n");
		$this->send("Content-Type: text/html\r\n");
		$this->send("Connection: close\r\n");
		$this->send("\r\n");
		$this->send($contents);
		$this->close();
	}
	
	public function onHeadersSended($data) {
		$this->raw_headers = explode("\r\n", $data);
		$this->processHeaders();
	}
	
	public function processHeaders() {
		$_SERVER = array();
		$raw_header = $this->raw_headers[0];
		preg_match('@^(GET|POST) (.*) HTTP/\d+\\.\\d+$@', $raw_header, $matches);
		$_SERVER['HTTP_METHOD'] = $matches[1];
		$_SERVER['HTTP_HOST'] = 'localhost';
		$_SERVER['HTTP_PORT'] = 80;
		$info = parse_url($matches[2]);
		$_SERVER['REQUEST_URI'] = @$info['path' ];
		$_SERVER['HTTP_QUERY']  = @$info['query'];
		parse_str($_SERVER['HTTP_QUERY'], $_GET);
		foreach (array_slice($this->raw_headers, 1) as $raw_header) {
			@list($type, $value) = explode(':', $raw_header, 2);
			$type = strtolower(trim($type));
			$value = trim($value);
			switch ($type) {
				case 'host':
					$info = parse_url($value);
					if ($info['host']) $_SERVER['HTTP_HOST'] = $info['host'];
					if ($info['port']) $_SERVER['HTTP_PORT'] = $info['port'];
				break;
				case 'content-length':
					$this->recvContentLength = (int)$value;
				break;
			}
		}
	}
}

class AppHttpSocketClient extends HttpSocketClient {
	public $sessions = array();

	public function outputHttpRequest() {
		echo '<pre>';
		//phpinfo();
		print_r($_SERVER);
		print_r($_GET);
		print_r($_POST);
		//print_r($_REQUEST);
		print_r($this->raw_headers);
	}
}

$socketServer = new SocketServer(80, 'AppHttpSocketClient');
$socketServer->loop();
