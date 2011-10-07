<?php

function __yaml_get_indent($line) {
	$indent = strspn($line, " \t");
	if (substr(ltrim($line), 0, 1) == '-') $indent++;
	return $indent;
}

function __yaml_get_value($string) {
	if ($string == '[]') return array();
	if (substr($string, 0, 1) == '"') {
		return stripslashes(substr($string, 1, -1));
	}
	return $string;
}

function __yaml_parse_lines($lines, $level = 0, &$n = 0) {
	$root = array();
	$base_indent = NULL;
	$last_root_index = -1;
	//echo "yaml_parse_lines({$level}, {$n})\n";
	$self = __FUNCTION__;
	
	for (; $n < count($lines); $n++) {
		//printf(":::: %s\n", $lines[$n]);
		$line = $lines[$n];
		$tline = trim($line);
		if ($tline == '') continue;
		if ($tline == '---') continue;
		
		$indent = __yaml_get_indent($lines[$n]);
		if ($base_indent === NULL) $base_indent = $indent;

		// Same indent
		if ($indent == $base_indent) {
			$line = trim($lines[$n]);
			if (strlen($line) == 0) continue;
			if ($line[0] == '-') {
				//echo "$last_root_index\n";
				$root[++$last_root_index] = __yaml_get_value(trim(substr($line, 1)));
			} else {
				list($name, $value) = explode(':', $line, 2);

				$root[$name] = __yaml_get_value(trim($value));
				$last_root_index = $name;
			}
		}
		// Higher indent
		else if ($indent > $base_indent) {
			//echo "++\n";
			$root[$last_root_index] = $self($lines, $level + 1, $n);
		}
		// Lower indent
		else if ($indent < $base_indent) {
			//echo "--\n";
			$n--;
			break;
		}
	}
	
	//printf("/\n");
	
	if (is_numeric($last_root_index)) {
		return $root;
	} else {
		return (object)$root;
	}
}

function yaml_parse($data) {
	return __yaml_parse_lines(explode("\n", $data));
}
