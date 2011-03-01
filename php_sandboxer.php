<?php

/*function call_hook() {
	$args = func_get_args();
	return call_user_func_array(array(Sandboxer::$last_instance, 'call_hook'), $args);
}*/

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
		
		$tokens = array_map(function($v) { return is_array($v) ? $v[1] : $v; }, token_get_all('<?php ' . $v));
		
		$l = count($tokens);
		$code = true;
		for ($n = 0; $n < $l; $n++) {
			$token = $tokens[$n];

			if ($token == '?>') {
				$code = false;
				continue;
			}
			
			//echo "'$token'\n";

			if ($token == '<?php ') {
				$code = true;
				continue;
			}
			//var_dump($code);
			if ($code) {
				if (preg_match('@^\\$?[a-z_]\\w*$@i', $token)) {
					// Function call.
					if ($tokens[$n + 1] == '(') {
						switch ($token) {
							case 'require': case 'require_once': case 'include': case 'include_once':
								die("Unhandled require, include...");
							break;
							case 'foreach':
							case 'while':
							case 'for':
							case 'if':
							case 'isset':
							case 'return':
							break;
							default:
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
		
		die("Unexpected function '{$func}'");
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
	
	public function execute_file($file_name) {
		array_push($this->file_name_stack, $file_name);
		{
			$code = file_get_contents($file_name);
			$this->__hook_eval('?>' . $code);
		}
		array_pop($this->file_name_stack);
	}
	
	public function registerErrorHandlers() {
		set_error_handler(array($this, 'errorHandler'), E_ALL | E_STRICT);
	}
	
	public function errorHandler($errno, $errstr, $errfile, $errline) {
		echo "PHP ERROR: $errno, $errstr, $errfile, $errline\n\n";
		exit;
	}
	
	public function register($name, $callback = NULL) {
		if ($callback === NULL) $callback = $name;
		$this->functions[$name] = $callback;
	}
}

$sandboxer = new Sandboxer();
$sandboxer->registerErrorHandlers();
$sandboxer->register('base64_decode');
$sandboxer->register('urldecode');
$sandboxer->register('fopen', function($name, $type) {
	if ($type != 'rb') die("Unexpected fopen type");
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
$sandboxer->execute_file('c:/projects/handler.php');
file_put_contents('handler_decrypted.php', '<?php ' . $sandboxer->unprocessed_code);