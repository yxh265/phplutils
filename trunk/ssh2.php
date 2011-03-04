<?php

class Ssh2Exception extends Exception {
}

class Ssh2 {
	public $handle;
	protected $_sftp;

	public function __construct() {
	}
	
	public function connect($host, $port = 22) {
		$this->handle = ssh2_connect($host, $port);
	}
	
	public function fingerprint($flags = -1) {
		if ($flags == -1) $flags = SSH2_FINGERPRINT_MD5 | SSH2_FINGERPRINT_HEX;
		return ssh2_fingerprint($this->handle, $flags);
	}
	
	public function force_fingerprint($expected_fingerprint) {
		$returned_fingerprint = $this->fingerprint();
		if ($returned_fingerprint != $expected_fingerprint) throw(new Ssh2Exception("HOSTKEY MISMATCH!\nPossible Man-In-The-Middle Attack?"));
	}

	public function auth_pubkey_file($username, $pubkeyfile, $privkeyfile, $passphrase) {
		@$ret = ssh2_auth_pubkey_file($this->handle, $username, $pubkeyfile, $privkeyfile, $passphrase);
		if (!$ret) throw(new Ssh2Exception("Authentication failed for {$username} using password"));
		return $ret;
	}
	
	public function auth_password($username, $password) {
		@$ret = ssh2_auth_password($this->handle, $username, $password);
		if (!$ret) throw(new Ssh2Exception("Authentication failed for {$username} using password"));
		return $ret;
	}
	
	public function exec() {
		$args = func_get_args();
		array_unshift($args, $this->handle);
		return call_user_func_array('ssh2_exec', $args);
	}

	public function exec_string() {
		$stream = call_user_func_array(array($this, 'exec'), func_get_args());
		stream_set_blocking($stream, true);
		return stream_get_contents($stream);
	}
	
	public function sftp() {
		$sftp = &$this->_sftp;
		if (!isset($sftp)) $sftp = new Ssh2Sftp($this, ssh2_sftp($this->handle));
		return $sftp;
	}
	
	public function __get($name) {
		if (method_exists($this, $name)) return $this->$name();
	}
}

class Ssh2Sftp {
	public $ssh;
	public $handle;
	public $pwd;

	public function __construct(Ssh2 $ssh, $handle) {
		$this->ssh = $ssh;
		$this->handle = $handle;
		$this->chdir('/');
	}
	
	static public function combine_path($base, $path) {
		// Not Absolute (relative)
		if (substr($path, 0, 1) != '/') $path = $base . '/' . $path;
		return $path;
	}
	
	public function chdir($path) {
		$path = static::combine_path($this->pwd, $path);
		if ($path != '/') $path = ssh2_sftp_realpath($this->handle, $path);
		$this->pwd = $path;
	}
	
	public function pwd() {
		return $this->pwd;
	}
	
	public function makeUri($path) {
		$path = static::combine_path($this->pwd, $path);
		//echo "{$path}\n";
		return "ssh2.sftp://{$this->handle}{$path}";
	}
	
	public function __call($name, $args) {
		static $methods = array('scandir', 'mkdir', 'fopen', 'file_get_contents');
		if (!in_array($name, $methods)) throw(new Ssh2Exception("Unknown sftp method '{$name}'"));
		$args[0] = $this->makeUri($args[0]);
		return call_user_func_array($name, $args);
	}
}