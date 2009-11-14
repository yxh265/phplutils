<?php
class AMF {
	static public function decode($f) {
		// Converts string into a stream. PHP >= 5.1
		if (is_string($f)) { $_f = fopen('php://memory', 'wb'); fwrite($_f, $f); fseek($_f, 0); $f = $_f; }

		// Version.
		$version = self::u16($f); assert('in_array($version, array(0, 3))');
		
		$refs = array('o' => array(), 's' => array(), 'd' => array()); // objects, strings, definitions

		// Header.
		$header_count = self::u16($f);
		assert('$header_count == 0'); // Header not processed yet.
		for ($n = 0; $n < $header_count; $n++) {
		}

		// Header.
		$body_count = self::u16($f);
		for ($n = 0; $n < $body_count; $n++) {
			$target   = self::utf8($f);
			$response = self::utf8($f);
			$length   = self::u32($f);
			self::decodeType($refs, $f);
		}
		
		echo "\n$version: $header_count, $body_count\n\n";
	}
	
	static public function encode($object) {
		throw(new Exception("Not implemented yet"));
	}

	static public function u8 ($f) { return ord(fread($f, 1)); }
	static public function u16($f) { return (($v = unpack('n', fread($f, 2))) === null) ? null : $v[1]; }
	static public function u32($f) { return (($v = unpack('N', fread($f, 4))) === null) ? null : $v[1]; }
	static public function dbl($f) { return (($v = unpack('d', strrev(fread($f, 8)))) === null) ? null : $v[1]; }
	static public function u29($f) {
		$v = 0; $shift = 0; $cur = 4;
		
		do {
			$cv = ord(fread($f, 1));
			$v |= ($cv & (($cur > 0) ? 0x7F : 0xFF)) << $shift;
			$shift += 7;
		} while (($cv & 0x80) && ($cur++ == 0));
		
		return $v;
	}
	static public function utf8($f) {
		return fread($f, self::u16($f));
	}

	static protected function decodeType(&$refs, $f, $type = -1) {
		if ($type == -1) $type = self::u8($f);
		$ret = null;
		printf("type: 0x%02X\n", $type);
		switch ($type) {
			case 0x00: break; // number
			case 0x01: break; // boolean
			case 0x02: break; // string
			case 0x03: break; // object Object
			//case 0x04: break; // 
			case 0x05: break; // null
			case 0x06: break; // undefined
			case 0x07: break; // Circular references are returned here
			case 0x08: break; // mixed array with numeric and string keys
			//case 0x09: break; //
			case 0x0A: // array
				$ret = array(); $refs['o'][] = &$ret;
				$len = self::u32($f);
				while ($len--) $ret[] = self::decodeType($refs, $f);
			break;
			case 0x0B: break; // date
			case 0x0C: break; // string, strlen(string) > 2^16
			case 0x0D: break;  // mainly internal AS objects
			//case 0x0E: break;  //
			case 0x0F: break;  // XML
			case 0x10: break;  // Custom Class
			case 0x11: // AMF3-specific
				$ret = self::decodeTypeAmf($refs, $f);
			break; 
			default: throw(new Exception(sprintf("Invalid amf type 0x%02X", $type)));
		}
		return $ret;
	}
	
	static protected function decodeTypeAmf(&$refs, $f, $type = -1) {
		if ($type == -1) $type = self::u8($f);
		printf("amf_type: 0x%02X\n", $type);
		$ret = null;
		switch ($type) {
			case 0x00: break; // undefined-marker
			case 0x01: break; // null-marker
			case 0x02: break; // false-marker
			case 0x03: break; // true-marker
			case 0x04: break; // integer-marker
			case 0x05: break; // double-marker
			case 0x06: // string-marker
				echo fread($f, 0x20); exit;
				$info = self::u29($f);
				$inline_str = !!($info & 1);
				
				// Reference to a previously defined string.
				if (!$inline_str) return $refs['s'][$info >> 1];

				// Inline string.
				$ret = fread($f, $info >> 1);
				$refs['s'][] = $ret;
			break;
			case 0x07: break; // xml-doc-marker
			case 0x08: break; // date-marker
			case 0x09: break; // array-marker
			case 0x0A: // object-marker
				die(fread($f, 0x40));
				$info = self::u29($f);
				$inline_obj = !!($info & 1);
				$inline_def = !!($info & 2);

				// Reference to a previously defined object.
				if (!$inline_obj) return $refs['o'][$info >> 1];

				// Inline object.
				if ($inline_def) {
					$ident = self::decodeTypeAmf($refs, $f, 0x06);
				}
				var_dump($inline_def);
				exit;
			break;
			case 0x0B: break; // xml-marker
			case 0x0C: break; // byte-array-marker
			default: throw(new Exception(sprintf("Invalid amf type 0x%02X", $type)));
		}
		return $ret;
	}
}
