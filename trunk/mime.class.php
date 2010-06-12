<?php

class Mime {
	protected $data;
	protected $cursor;
	public $info;

	public function __construct($data) {
		$this->data = $data;
		$this->cursor = 0;
		$this->info = array();
		$info = &$this->info;
		$last = '';
		$content = '';
		while (!$this->eof()) {
			$line = $this->readline();
			if (preg_match('@^([\\w-]+):(.*)$@', $line, $matches)) {
				$key   = strtolower($matches[1]);
				if (!isset($info[$key])) {
					$last = &$info[$key];
				} else {
					if (!is_array($info[$key])) $info[$key] = array($info[$key]);
					$last = &$info[$key][];
				}
				$last = trim($matches[2]);
			} else {
				if (strlen($line)) {
					$last .= ' ' . trim($line);
				} else {
					$content = $this->readleft();
				}
			}
		}
		
		if (preg_match('@^multipart/(mixed|alternative); boundary=(?P<boundary>.*)$@', $info['content-type'], $matches)) {
			$boundary = "--{$matches['boundary']}";
			$info['content'] = array();
			foreach (array_slice(explode($boundary, $content), 1) as $part) {
				if (substr($part, 0, 2) == '--') break;
				$mime = new Mime(ltrim($part));
				$info['content'][] = $mime->info;
			}
			return;
		}

		if (isset($info['content-transfer-encoding'])) {
			switch ($info['content-transfer-encoding']) {
				case 'base64':
					$info['content'] = base64_decode($content);
				break;
				case 'quoted-printable':
					$info['content'] = quoted_printable_decode($content);
				break;
				case '': default:
					$info['content'] = $content;
				break;
			}
		} else {
			$info['content'] = $content;
		}
	}
	
	public function eof() {
		return ($this->cursor === false);
	}
	
	public function readleft() {
		if ($this->eof()) return false;
		$data = substr($this->data, $this->cursor);
		$this->cursor = false;
		return $data;
	}

	public function readline() {
		if ($this->eof()) return false;
		$ppos = $this->cursor;
		$pos = strpos($this->data, "\r\n", $this->cursor);
		if ($pos === false) {
			$this->cursor = false;
			return false;
		}
		$this->cursor = $pos + 2;
		return substr($this->data, $ppos, $pos - $ppos);
	}

	static public function parse($data) {
		$mime = new Mime($data);
		return $mime->info;
	}
}

//print_r(Mime::parse(file_get_contents('emails/4.txt')));
