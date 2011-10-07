<?php

// Based on ruby's "marshal.c"

function fread1_signed($f) { return ($v = unpack('c', fread($f, 1))) ? $v[1] : NULL; }
function fread1_unsigned($f) { return ($v = unpack('C', fread($f, 1))) ? $v[1] : NULL; }

class RubyMarshalLoad {
	const MARSHAL_MAJOR   = 4;
	const MARSHAL_MINOR   = 8;

	const TYPE_NIL	= '0';
	const TYPE_TRUE	= 'T';
	const TYPE_FALSE	= 'F';
	const TYPE_FIXNUM	= 'i';

	const TYPE_EXTENDED	= 'e';
	const TYPE_UCLASS	= 'C';
	const TYPE_OBJECT	= 'o';
	const TYPE_DATA       = 'd';
	const TYPE_USERDEF	= 'u';
	const TYPE_USRMARSHAL= 	'U';
	const TYPE_FLOAT	= 'f';
	const TYPE_BIGNUM	= 'l';
	const TYPE_STRING	= '"';
	const TYPE_REGEXP	= '/';
	const TYPE_ARRAY	= '[';
	const TYPE_HASH	= '{';
	const TYPE_HASH_DEF	= '}';
	const TYPE_STRUCT	= 'S';
	const TYPE_MODULE_OLD	= 'M';
	const TYPE_CLASS	= 'c';
	const TYPE_MODULE	= 'm';

	const TYPE_SYMBOL	= ':';
	const TYPE_SYMLINK	= ';';

	const TYPE_IVAR	= 'I';
	/*
	*/
	public function __construct() {
	}
	
	public function load($f) {
		list(,$major, $minor) = unpack('C*', fread($f, 2));
		if ($major != self::MARSHAL_MAJOR || $minor != self::MARSHAL_MINOR) throw(new Exception("Invalid binary file"));
		return $this->readEntry($f);
	}

	public function readEntry($f, &$ivp = NULL) {
		$type = fread($f, 1);
		switch ($type) {
			case self::TYPE_NIL  : return NULL;
			case self::TYPE_TRUE : return true;
			case self::TYPE_FALSE: return false;
			case self::TYPE_FIXNUM:
				return $this->readFixNum($f);
			case self::TYPE_IVAR:
				$ivar = true;
				$v = $this->readEntry($f, $ivar);
				if ($ivar) {
					$len = $this->readFixNum($f);
					while ($len--) {
						$this->readSymbol($f);
						$this->readObject($f);
					}
					//throw(new Exception("aaaaaaaaaaaa"));
				}
				// @TODO Load IVAR
				return $v;
			case self::TYPE_ARRAY:
				$len = $this->readFixNum($f);
				$array = array();
				while ($len--) {
					$array[] = $this->readEntry($f, $ivp);
				}
				return $array;
			case self::TYPE_STRING:
				$len = $this->readFixNum($f);
				$data = fread($f, $len);
				return $data;
			case self::TYPE_OBJECT:
				return $this->readSymbol($f);
			default:
				throw(new Exception("Unhandled type '{$type}'"));
		}
	}
	
	public function readIVar() {
		
	}
	
	public function readSymbol($f) {
		$ivar = 0;
		while (true) {
			$type = fread($f, 1);
			switch ($type) {
				case self::TYPE_IVAR:
					$ivar = 1;
					continue;
				case self::TYPE_SYMBOL:
					return $this->readSymbolReal($f, $ivar);
				case self::TYPE_SYMLINK:
					if ($ivar) throw(new Exception("dump format error (symlink with encoding)"));
					return $this->readSymbolLink($f);
				default:
					throw(new Exception(sprintf("dump format error for symbol(0x%x)", ord($type))));
			}
		}
	}

	public function readObject($f) {
	}
	
	public function readFixNum($f) {
		$c = fread1_signed($f);
		if ($c == 0) return 0;
		if ($c > 0) {
			if ($c > 4 && $c < 128) return $c - 5;
			$x = 0;
			for ($i = 0; $i < $c; $i++) {
				$x |= fread1_unsigned($f) << ($i * 8);
			}
		} else {
			if ($c > -129 && $c < -4) return $c + 5;
			$c = -$c;
			$x = -1;
			for ($i = 0; $i < $c; $i++) {
				$x &= ~(0xff << (8 * $i));
				$x |= fread1_unsigned($f) << (8 * $i);
			}
		}
		return $x;
	}
}

$rubyMarshalLoad = new RubyMarshalLoad();
$rubyMarshalLoad->load(fopen('Map001.rxdata', 'rb'));
//var_dump($rubyMarshalLoad->load(fopen('test.dump', 'rb')));