<?php

function function_proxy_handle($v) {
	$ret = preg_replace_callback('@([\\$\\w]+\\s*)\\(@', function($fname) {
		$escaped_fname = $fname = $fname[1];
		
		// Not a variable
		if (substr($fname, 0, 1) != '$') {
			$escaped_fname = var_export($fname, true);
		}

		return 'function_proxy(' . $escaped_fname . ',';
	}, $v);
	
	return $ret;
}

function function_proxy($func) {
	static $enable_funcs = array(
		'base64_decode',
		'fopen',
		'fread'
	);

	$args = array_slice(func_get_args(), 1);
	
	echo "{$func}(";
	foreach ($args as $k => $arg) {
		if ($k > 0) echo ", ";
		echo var_export($arg, true);
	}
	echo ")\n";
	echo "\n";
	
	if (in_array($func, $enable_funcs)) {
		return call_user_func_array($func, $args);
	}
	
	if ($func == 'eval') {
		$code = function_proxy_handle($args[0]);
		echo $code . "\n\n";
		extract($GLOBALS['LOCAL_CONTEXT']);
		$__RETVAL = eval($code);
		$GLOBALS['LOCAL_CONTEXT'] = get_defined_vars();
		return $__RETVAL;
	}
	
	die("Unexpected function '{$func}'");
}