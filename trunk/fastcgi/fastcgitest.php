<?php

function freadv($f) {
	$v = 0;
	$offset = 0;
	do {
		$c = ord(fread($f, 1));
		$v |= (($c & 0xFF) << $offset);
		$offset += 8;
	} while ($c & 0x80);
	return $v;
}

class FastcgiRequest {
	public $id;
	public $params;
	public $stdin;
	public $_paramsStream;
	public $_stdinStream;
	
	public function __construct($id) {
		$this->id = $id;
		$this->_paramsStream = fopen('php://temp', 'r+b');
		$this->_stdinStream = fopen('php://temp', 'r+b');
	}
	
	public function parseParams() {
		fseek($this->_paramsStream, 0);
		$this->params = array();

		while (!feof($this->_paramsStream)) {
			$keyLength   = freadv($this->_paramsStream);
			$valueLength = freadv($this->_paramsStream);
			$key   = ($keyLength > 0) ? fread($this->_paramsStream, $keyLength) : '';
			$value = ($valueLength > 0) ? fread($this->_paramsStream, $valueLength) : '';
			$this->params[$key] = $value;
		}
	}
}

class FastcgiClient extends SocketClient {
	const FCGI_BEGIN_REQUEST     = 1;
	const FCGI_ABORT_REQUEST     = 2;
	const FCGI_END_REQUEST       = 3;
	const FCGI_PARAMS            = 4;
	const FCGI_STDIN             = 5;
	const FCGI_STDOUT            = 6;
	const FCGI_STDERR            = 7;
	const FCGI_DATA              = 8;
	const FCGI_GET_VALUES        = 9;
	const FCGI_GET_VALUES_RESULT = 10;
	const FCGI_UNKNOWN_TYPE      = 11;
	
	const HEADER_FORMAT = "Cversion/Ctype/nrequestId/ncontentsLength/cpaddingLength";

	protected function handleRequest(FastcgiRequest $request) {
	}
	
	protected $dataBuffer;
	protected $params;
	protected $requests = array();
	
	protected function handlePacket($type, $requestId, $data) {
		$request = &$this->requests[$requestId];
	
		switch ($type) {
			default:
				echo "UNKNOWN FASTCGI: $type, $requestId\n";
			break;
			case FastcgiClient::FCGI_BEGIN_REQUEST:
				$request = new FastcgiRequest($requestId);
			break;
			case FastcgiClient::FCGI_PARAMS:
				//echo "$data\n";
				
				if (strlen($data) == 0) {
					$request->parseParams();
				} else {
					fwrite($request->_paramsStream, $data);
				}
			break;
			case FastcgiClient::FCGI_STDIN:
				//echo "$data\n";
				if (strlen($data) == 0) {
					$this->_handleRequest($request);
					// End stdin.
				} else {
					fwrite($request->_stdinStream, $data);
				}
			break;
		}
	}
	
	protected function _handleRequest(FastcgiRequest $request) {
		$client = $this;
		$output = '';
		ob_start(function($data) use ($request, $client, &$output) {
			$client->writeChunk($request->id, FastcgiClient::FCGI_STDOUT, $data);
			//$output .= $data;
		}, 8096);
		$this->handleRequest($request);
		ob_end_flush();

		$this->writeChunk($request->id, FastcgiClient::FCGI_STDOUT, '');
		$this->writeChunk($request->id, FastcgiClient::FCGI_STDERR, '');
		$this->writeChunk($request->id, FastcgiClient::FCGI_END_REQUEST, pack('Nc4', 0, 0, 0, 0, 0));
		$this->close();
	}
	
	public function writeChunk($requestId, $type, $data = '') {
		$padding = str_repeat("\0", (4 - strlen($data) % 4) % 4);
		//if (((strlen($data) + strlen($padding)) % 4) != 0) throw(new Exception("Invalid packet"));
		$this->write(pack("C2n2c2", //array(
			/*'version'        => */1,
			/*'type'           => */$type,
			/*'requestId'      => */$requestId,
			/*'contentsLength' => */strlen($data),
			/*'paddingLength'  => */strlen($padding),
			0
		//)
		) . $data . $padding);
	}

	public function onRead($_data) {
		$this->dataBuffer .= $_data;

		while (strlen($this->dataBuffer) >= 8) {
			$info = unpack(FastcgiClient::HEADER_FORMAT, substr($this->dataBuffer, 0, 8));
			$packetSize = 8 + $info['contentsLength'] + $info['paddingLength'];
			if (strlen($this->dataBuffer) < $packetSize) return;

			$this->handlePacket($info['type'], $info['requestId'], substr($this->dataBuffer, 8, $info['contentsLength'] - 8));
			$this->dataBuffer = substr($this->dataBuffer, $packetSize);
		}
	}
}

class MyFastcgiClient extends FastcgiClient {
	public function handleRequest(FastcgiRequest $request) {
		$start = microtime(true);
		echo "HTTP/1.1 200 OK\r\n\r\n";
		echo "Hello World!\n";
		$end = microtime(true);
		printf("%.6f\n", $end - $start);
	}
}

class SocketClient {
	protected $socket;
	public $id;
	public $buffer;
	
	public function __construct($socket) {
		$this->socket = $socket;
		//stream_set_blocking($socket, 0);
	}
	
	public function onRead($data) {
	}
	
	public function write($data) {
		fwrite($this->socket, $data);
		//event_buffer_write($this->buffer, $data);
	}
	
	public function close() {
		event_buffer_disable($this->buffer, EV_READ | EV_WRITE);
		event_buffer_free($this->buffer);
		fclose($this->socket);
	}
}

class SocketServer {
	protected $socket;
	protected $clientClass;
	public $clients = array();

	public function __construct($clientClass = 'SocketClient') {
		$this->clientClass = $clientClass;
	}
	
	public function listen($ip, $port) {
		$this->socket = stream_socket_server('tcp://' . $ip . ':' . $port, $errno, $errstr);
		stream_set_blocking($this->socket, 0);
		$base = event_base_new();
		$event = event_new();
		event_set($event, $this->socket, EV_READ | EV_PERSIST, array($this, 'onAccept'), $base);
		event_base_set($event, $base);
		event_add($event);
		event_base_loop($base);
	}
	
	protected function newClient($clientSocket) {
	}
	
	protected function onAccept($serverSocket, $flag, $base) {
		static $lastId = 0;
	
		//echo "onAccept\n";
		$clientClass = $this->clientClass;
		$client = new $clientClass($clientSocket = stream_socket_accept($serverSocket));
		$client->id = $lastId;

		$buffer = event_buffer_new($clientSocket, function($buffer, $client) {
			$data = '';
			while (strlen($temp = event_buffer_read($buffer, 2048))) {
				//echo "onRead(" . strlen($temp) . ")\n";
				$data .= $temp;
			}
			$client->onRead($data);
		}, NULL, function($buffer, $error, $client) {
			echo "onError\n";
		}, $client);
		//$buffer = event_buffer_new($clientSocket, 'ev_read', NULL, 'ev_error', $lastId);
		event_buffer_base_set($buffer, $base);
		event_buffer_timeout_set($buffer, 30, 30);
		event_buffer_watermark_set($buffer, EV_READ, 0, 0xffffff);
		event_buffer_priority_set($buffer, 10);
		event_buffer_enable($buffer, EV_READ | EV_PERSIST);
		
		$client->buffer = $buffer;
		
		$this->clients[$lastId] = $client;
		
		$lastId++;
	}
}

$socketServer = new SocketServer('MyFastcgiClient');
$socketServer->listen('127.0.0.1', 9001);
