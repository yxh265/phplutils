<?php

// http://www.gnu.org/software/gettext/manual/gettext.html#MO-Files
class GettextMoGenerator {
	/**
	 * @var $strings List of strings as an associative array. Keys are original strings and values the translation strings.
	 */
	public $strings = array();
	
	/**
	 * Writes the .mo contents into a file.
	 *
	 * @param string $file File name of the mo file to write.
	 *
	 * @return stream
	 */
	public function write($file) {
		if (!function_exists('mb_internal_encoding')) return $this->_write($file);

		$mb_encoding = mb_internal_encoding();
		mb_internal_encoding('8bit');
		{
			try { $r = $this->_write($file); } catch (Exception $e) { }
		}
		mb_internal_encoding($mb_encoding);
		
		if (isset($e)) throw($e); else return $r;
	}

	private function _write($file) {
		if (!($f = fopen($file, 'wb'))) throw(new Exception("Can't open mo file '{$file}' for writing"));

		$strings_info_length = (4 + 4) * count($this->strings);

		$original_offset      = 0x1C + $strings_info_length * 0;
		$translation_offset   = 0x1C + $strings_info_length * 1;
		$original_text_offset = 0x1C + $strings_info_length * 2;
		$text_offset = $original_text_offset;

		// Depending on the machine byte order (L=32 bit unsigned integer machine dependant, S=16 bit unsigned integer machine dependat)
		fwrite($f, pack('LSSLLLLL',
			$magic = 0x950412de,
			$version = 0, $revision = 0,
			$number_of_strings = count($this->strings),
			$original_offset,
			$translation_offset,
			$size_hash_table = 0,
			$size_hash_offset = 0
		));
		
		// To avoid require of a hash table.
		ksort($this->strings, SORT_STRING);

		$keys   = new ArrayObject(array_keys($this->strings));
		$values = new ArrayObject(array_values($this->strings));
		for ($pass = 0; $pass <= 1; $pass++) {
			foreach (array($keys, $values) as $string_list) {
				if ($pass == 0) {
					$buffer = '';
					foreach ($string_list as $string) {
						$string_length = strlen($string);
						//fwrite($f, pack('LL', $string_length, $text_offset));
						$buffer .= pack('LL', $string_length, $text_offset);
						$text_offset += $string_length + 1;
					}
					fwrite($f, $buffer);
				} else {
					//foreach ($string_list as $string) fwrite($f, "{$string}\0");
					fwrite($f, implode("\0", (array)$string_list) . "\0");
				}
			}
		}
		
		return $f;
	}
	
	/**
	 * Returns the mo file as a string.
	 *
	 * @return string
	 */
	public function getAsString() {
		$f = $this->write('php://temp');
		fseek($f, 0, SEEK_END); $fsize = ftell($f);
		fseek($f, 0, SEEK_SET);
		return fread($f, $fsize);
	}

	/**
	 * Acts as a gettext function using the translations in this object.
	 *
	 * @return string
	 */
	public function _($o) {
		$t = &$this->strings[$o];
		return isset($t) ? $t : $o;
	}
	
	/**
	 * Obtains a GettextMoGenerator instance with the $strings field filled with the contents of the .po.
	 *
	 * @todo Implement
	 *
	 * @return string
	 */
	static public function fromPo($file) {
		$f = fopen($file, 'rb');
		if (!$f) throw(new Exception("Can't open po file '{$file}' for reading"));
		$mo = new self();
		
		$msgid = '';
		while (!feof($f)) {
			$line = trim(fgets($f));
			if (!strlen($line) || ($line[0] == '#')) continue; // Ignore empty lines and comments
			if (preg_match('@^((msgid|msgstr)\\s+)?"(.*)"$@', $line, $matches)) {
				list(,,$type,$text) = $matches;
				$text = stripcslashes($text);
				switch ($type) {
					case 'msgid':
						$msgid = $text;
					break;
					case 'msgstr':
						$s = &$mo->strings[$msgid];
						if (!isset($s)) $s = '';
						$s .= $text;
					break;
				}
			}
		}
		
		return $mo;
	}
}

/*
GettextMoGenerator::fromPo('test.po')->write('test.mo');
*/

/*
$mo = new GettextMoGenerator();
//$mo->strings['test'] = 'prueba';
$mo->strings['Genres'] = 'Géneros';
$mo->write('test.mo');
//echo $mo->getAsString();
*/
