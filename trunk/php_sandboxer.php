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
	);
	
	echo "$func\n";
	
	$args = array_slice(func_get_args(), 1);
	
	if (in_array($func, $enable_funcs)) {
		return call_user_func_array($func, $args);
	}
	
	if ($func == 'eval') {
		$code = function_proxy_handle($args[0]);
		echo $code;
		return eval($code);
	}
	
	die("Unexpected function '{$func}'");
}