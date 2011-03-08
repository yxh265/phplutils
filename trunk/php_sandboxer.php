<?php

class SandboxerException extends Exception {
}

class SandboxerTokenizer {
	public $tokens;
	public $n;

	public function __construct($v) {
		$this->tokens = array_map(function($v) { return is_array($v) ? $v[1] : $v; }, token_get_all('<' . '?php ' . $v));
		$this->n = 0;
	}

	public function current() {
		return $this->tokens[$this->n];
	}

	public function prev() {
		$this->moveDirection(-1);
		return $this->current();
	}

	public function next() {
		$this->moveDirection(+1);
		return $this->current();
	}

	public function moveDirection($n) {
		if ($n < 0) $sign = -1; else if ($n > 0) $sign = +1; else return;
		while (true) {
			$this->n += $sign;
			if (!isset($this->tokens[$n])) break;
			if (!preg_match('@^\\s+$@', $this->tokens[$n])) {
				return;
			}
		}
	}
}

class Sandboxer {
	static public $last_instance;
	public $file_name_stack = array();
	public $local_context = array();
	public $code, $unprocessed_code, $__RETVAL;
	public $functions = array();

	public function __construct() {
		static::$last_instance = $this;
	}

	public function getCurrentFileName() {
		return $this->file_name_stack[count($this->file_name_stack) - 1];
	}

	public function call_replacer($v) {
		$className = get_called_class();

		$tokens = array_map(function($v) { return is_array($v) ? $v[1] : $v; }, token_get_all('<' . '?php ' . $v));

		$l = count($tokens);
		$code = true;
		$code_state = $code_state_init = array(
			'function' => false,
		);
		for ($n = 0; $n < $l; $n++) {
			$token = $tokens[$n];

			if ($token == '?' . '>') {
				$code = false;
				continue;
			}

			//echo "'$token'\n";

			if ($token == '<' . '?php ') {
				$code = true;
				$code_state = $code_state_init;
				continue;
			}
			//var_dump($code);
			if ($code) {
				// Space
				if (preg_match('@^\\s+$@', $token)) {
					continue;
				}
				if (preg_match('@^\\$?[a-z_]\\w*$@i', $token)) {
					// Function call.
					if ($tokens[$n + 1] == '(') {
						switch ($token) {
							case 'require': case 'require_once': case 'include': case 'include_once':
								throw(new SandboxerException("Unhandled require, include..."));
							break;
							case 'foreach':
							case 'while':
							case 'for':
							case 'if':
							case 'isset':
							case 'return':
							break;
							default:
								if ($code_state['function']) {
									break;
								}

								if (($tokens[$n - 1] == '::') || ($tokens[$n - 1] == '->')) {
									throw(new SandboxerException("Not supported sandbox method calling"));
								}

								$escaped_token = $token;
								if (substr($token, 0, 1) != '$') {
									$escaped_token = var_export($token, true);
								}
								if ($token == 'eval') {
									$escaped_token = '((Sandboxer::$last_instance->local_context = get_defined_vars()) === NULL) ? NULL : ' . $escaped_token;
								}
								$tokens[$n] = $className . '::$last_instance->call_hook(' . $escaped_token . ',';
								$tokens[$n + 1] = '';
								//echo $token . "\n";
							break;
						}
					}

					if ($token == '__FILE__') {
						$tokens[$n] = var_export($this->getCurrentFileName(), true);
					}
				}

				if ($token == 'function') {
					$code_state['function'] = true;
				} else {
					$code_state['function'] = false;
				}

			}
		}

		return implode('', array_slice($tokens, 1));
	}

	public function call_hook($func) {
		$args = array_slice(func_get_args(), 1);

		echo "{$func}(";
		foreach ($args as $k => $arg) {
			if ($k > 0) echo ", ";
			echo var_export($arg, true);
		}
		echo ")\n";
		echo "\n";

		$hooked_func = &$this->functions[$func];

		if (isset($hooked_func)) {
			return call_user_func_array($hooked_func, $args);
		}

		if (method_exists($this, $method_name = '__hook_' . $func)) {
			return call_user_func_array(array($this, $method_name), $args);
		}

		throw(new SandboxerException("Unexpected function '{$func}'"));
	}

	public function __hook_eval($unprocesed_code) {
		$this->code = $this->call_replacer($this->unprocessed_code = $unprocesed_code);
		unset($unprocesed_code);
		echo "------------------------------------------------\n";
		echo $this->code . "\n";
		echo "------------------------------------------------\n";
		extract(Sandboxer::$last_instance->local_context);
		//print_r($this->local_context);
		$this->__RETVAL = eval($this->code);
		//print_r(get_defined_vars());
		Sandboxer::$last_instance->local_context = get_defined_vars();
		return $this->__RETVAL;
	}

	/**
	 * Execute a file within the current context.
	 *
	 * @param  string  $file_name
	 */
	public function execute_file($file_name) {
		array_push($this->file_name_stack, $file_name);
		{
			$code = file_get_contents($file_name);
			$this->__hook_eval('?' . '>' . $code);
		}
		array_pop($this->file_name_stack);
	}

	public function registerErrorHandlers() {
		return set_error_handler(array($this, 'errorHandler'), E_ALL | E_STRICT);
	}

	public function errorHandler($errno, $errstr, $errfile, $errline) {
		echo "PHP ERROR: $errno, $errstr, $errfile, $errline\n\n";
		exit;
	}

	/**
	 * Register a function that will be allowed in the function.
	 * Specifying a callback will use that code for the function.
	 *
	 * @param  string    $name
	 * @param  function  $callback
	 */
	public function register($name, $callback = NULL) {
		if ($callback === NULL) $callback = $name;
		$this->functions[$name] = $callback;
	}

	/**
	 * Tries to decrypt a file in a common way.
	 *
	 * @param  string  $file_to_execute
	 * @param  string  $redefined_host
	 */
	static public function generic_decrypter($file_to_execute, $redefined_host = NULL) {
		if ($redefined_host === NULL) $redefined_host = $_SERVER['HTTP_HOST'];

		$original_http_host = $_SERVER['HTTP_HOST'];
		$_SERVER['HTTP_HOST'] = $argv[1];
		{
			$sandboxer = new Sandboxer();
			$sandboxer->registerErrorHandlers();
			$sandboxer->register('base64_decode');
			$sandboxer->register('urldecode');
			$sandboxer->register('fopen', function($name, $type) {
				if ($type != 'rb') throw(new SandboxerException("Unexpected fopen type"));
				return fopen($name, 'rb');
			});
			$sandboxer->register('fread');
			$sandboxer->register('fclose');
			$sandboxer->register('strtr');
			$sandboxer->register('preg_match');
			$sandboxer->register('str_replace');
			$sandboxer->register('die', function($v) {
				die($v);
			});

			try {
				$sandboxer->execute_file($file_to_execute);
			} catch (SandboxerException $e) {
				printf("Exception: %s\n", $e->getMessage());
			}

			return '<' . '?php ' . $sandboxer->unprocessed_code;
		}

		// Restore original host.
		$_SERVER['HTTP_HOST'] = $original_http_host;
	}
}

// file_put_contents('handler_decrypted.php', Sandboxer::generic_decrypter('c:/projects/handler.php', $argv[1]));
