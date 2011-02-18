<?php

interface ICache {
	static public function available();
	public function get($key, $ttl, $callback);
}

class NullCache implements ICache {
	public function __construct($info = NULL) {
	}

	static public function available() {
		return true;
	}

	public function get($key, $ttl, $callback) {
		return $callback();
	}
}

class ApcCache extends NullCache {
	public function __construct($info = NULL) {
	}

	static public function available() {
		return function_exists('apc_fetch');
	}

	public function get($key, $ttl, $callback) {
		if (($out = apc_fetch($key, $success)) === false) {
			$out = $callback();
			apc_store($key, $out, $ttl);
		}
		return $out;
	}
}

class FileCache extends NullCache {
	protected $cache_folder;

	public function __construct($path = NULL) {
		if ($path === NULL) {
			if (is_dir('/dev/shm/')) {
				$path = '/dev/shm/';
			} else {
				$path = sys_get_temp_dir() . '/php_file_cache';
			}
		}
		$this->cache_folder = $path;
		if (!is_dir($this->cache_folder)) mkdir($this->cache_folder, 0777);
	}
	
	static public function available() {
		return true;
	}

	public function get($key, $ttl, $callback) {
		$cache_file = $this->cache_folder . '/' . md5($key) . '.txt';
		$out = false;

		if (is_file($cache_file)) {
			if ((time() - filemtime($cache_file)) < $ttl) {
				@$out = unserialize(file_get_contents($cache_file));
			}
		}
		
		if ($out === false) {
			$out = $callback();
			@file_put_contents($cache_file, serialize($out));
		}

		return $out;
	}
}

class CacheFactory {
	static $drivers = array('apc' => 'ApcCache', 'file' => 'FileCache', 'null' => 'NullCache');

	static public function getInstance($driver_name = 'autodetect', $info = NULL) {
		if ($driver_name == 'autodetect') {
			foreach (static::$drivers as $name => $class) {
				if ($class::available()) {
					$driver_name = $name;
					break;
				}
			}
		}
		
		$driver_class = &static::$drivers[$driver_name];
		
		if (!isset($driver_class)) throw(new Exception("Invalid CacheFactory::driver_name"));
		return new $driver_class($info);
	}
}
