<?php

class MimeDocument {
	public $filename;
	public $headers;
	public $content;
	public $childs;

	public function isAttachment() {
		return strlen($this->filename);
	}
	
	public function getContent() {
		if ($this->content !== null) return $this->content;
		return count($this->childs) ? $this->childs[0]->getContent() : null;
	}

	public function getSubject() {
		return $this->headers['subject'];
	}

	public function getFrom() {
		return $this->headers['from'];
	}

	public function getTo() {
		return $this->headers['to'];
	}
	
	public function getDate() {
		return strtotime($this->headers['date']);
	}

	public function getAttachments() {
		$attachments = array();
		if ($this->isAttachment()) $attachments[basename($this->filename)] = $this->getContent();
		foreach ($this->childs as $child) if ($child->isAttachment()) $attachments[basename($child->filename)] = $child->getContent();
		return $attachments;
	}
}

class Mime {
	protected $data;
	protected $cursor;
	public $document;

	public function __construct($data) {
		$this->data = $data;
		$this->cursor = 0;
		$this->document = new MimeDocument;
		$headers = array();
		$document = &$this->document;
		$last = '';
		$content = '';
		while (!$this->eof()) {
			$line = $this->readline();
			if (preg_match('@^([\\w-]+):(.*)$@', $line, $matches)) {
				$key   = strtolower($matches[1]);
				if (!isset($headers[$key])) {
					$last = &$headers[$key];
				} else {
					if (!is_array($headers[$key])) $headers[$key] = array($headers[$key]);
					$last = &$headers[$key][];
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
		
		$document = new MimeDocument;
		$document->headers = $headers;
		$document->filename = '';
		$document->content = null;
		$document->childs = array();

		if (isset($headers['content-type'])) {
			if (preg_match('@^multipart/(mixed|alternative); boundary=(?P<boundary>.*)$@', $headers['content-type'], $matches)) {
				$boundary = "--{$matches['boundary']}";
				foreach (array_slice(explode($boundary, $content), 1) as $part) {
					if (substr($part, 0, 2) == '--') break;
					$mime = new Mime(ltrim($part));
					$document->childs[] = $mime->document;
				}
				return;
			}
		}

		if (isset($headers['content-type'])) {
			if (preg_match('@name=\"(.*)\"@', $headers['content-type'], $matches)) {
				$document->filename = basename($matches[1]);
			}
		}

		if (isset($headers['content-disposition'])) {
			if (preg_match('@attachment; filename=\"(.*)\"@', $headers['content-disposition'], $matches)) {
				$document->filename = basename($matches[1]);
			}
		}

		$document->content = $content;
		if (isset($headers['content-transfer-encoding'])) {
			switch ($headers['content-transfer-encoding']) {
				case 'base64':
					$document->content = base64_decode($content);
				break;
				case 'quoted-printable':
					$document->content = quoted_printable_decode($content);
				break;
			}
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
		return $mime->document;
	}
}

/*
$document = Mime::parse(file_get_contents('emails/4.txt'));
print_r($document->getSubject());
print_r($document->getFrom());
print_r($document->getTo());
print_r($document->getContent());
print_r($document->getAttachments());
*/
