<?php

/*function call_hook() {
	$args = func_get_args();
	return call_user_func_array(array(Sandboxer::$last_instance, 'call_hook'), $args);
}*/

class Sandboxer {
	static public $last_instance;
	public $file_name_stack = array();
	public $local_context = array();
	private $code, $__RETVAL;
	public $functions = array();
	
	public function __construct() {
		static::$last_instance = $this;
	}

	public function call_replacer($v) {
		$class = get_called_class();
		
		// @TODO: Use tokenizer.
		
		$ret = preg_replace_callback('@([\\$\\w]+)\\s*\\(@', function($fname) use ($class) {
			$escaped_fname = $fname = $fname[1];

			switch ($fname) {
				// Keyword
				case 'foreach':
				case 'while':
				case 'for':
				case 'if':
					return $fname . '(';
				break;
				// Not a keyword
				default:
					//echo "'{$fname}'\n\n";

					// Not a variable
					if (substr($fname, 0, 1) != '$') {
						$escaped_fname = var_export($fname, true);
					}

					// Capture local context before eval.
					if ($fname == 'eval') {
						$escaped_fname = '((Sandboxer::$last_instance->local_context = get_defined_vars()) === NULL) ? NULL : ' . $escaped_fname ;
					}
					
					return $class . '::$last_instance->call_hook(' . $escaped_fname . ',';
					//return 'call_hook(' . $escaped_fname . ',';
				break;
			}
		}, $v);
		
		$ret = str_replace('__FILE__', var_export($this->file_name_stack[count($this->file_name_stack) - 1], 1), $ret);
		
		return $ret;
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
		$this->code = $this->call_replacer($unprocesed_code);
		unset($unprocesed_code);
		echo $this->code . "\n\n";
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
$sandboxer->register('strtr');
$sandboxer->execute_file('c:/projects/handler.php');