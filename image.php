<?php
	class Image {
		const PNG  = IMAGETYPE_PNG;
		const JPEG = IMAGETYPE_JPEG;
		const GIF  = IMAGETYPE_GIF;
		const AUTO = -1;
		
		private static $map = array(self::GIF => 'gif', self::PNG => 'png', self::JPEG => 'jpeg');
		private static $map_r = array('gif' => self::GIF, 'png' => self::PNG, 'jpeg' => self::JPEG, 'jpg' => self::JPEG);
	
		public $i;
		public $x, $y;
		public $w, $h;
		
		function __construct($w = null, $h = null, $bpp = 32) {
			if (empty($w) && empty($h)) return;
			$this->w = $w; $this->h = $h;
			switch ($bpp) {
				case 32: case 24: default:
					$i = $this->i = ImageCreateTrueColor($w, $h);
					if ($bpp == 32) {
						ImageSaveAlpha($i, true);
						ImageAlphaBlending($i, false);
						Imagefilledrectangle($i, 0, 0, $w, $h, imagecolorallocatealpha($i, 0, 0, 0, 0x7f));
						ImageAlphaBlending($i, true);
					}
				break;
				case 8:
					$i = $this->i = imagecreate($w, $h);
				break;
			}
		}
		
		static function fromFile($url) {
			$i = new Image();
			list($i->w, $i->h, $type) = getimagesize($url);
			if (!isset(self::$map[$type])) throw(new Exception('Invalid file format'));
			$call = 'imagecreatefrom' . self::$map[$type];
			$i->i = $call($url);
			ImageSaveAlpha($i->i, true);
			return $i;
		}
		
		function getPos($x, $y) {
			if ($x < 0 || $y > 0)
			return array(-1, -1);
		}
		
		function checkBounds($x, $y) {
			return ($x < 0 || $y < 0 || $x >= $this->w || $y >= $this->h);
		}
		
		function get($x, $y) {
			if ($this->checkBounds($x, $y)) return -1;
			return imageColorAt($i, $x + $this->x, $y + $this->y);
		}
		
		function color($r = 0x00, $g = 0x00, $b = 0x00, $a = 0xFF) {
			if (is_string($r)) sscanf($r, '#%02X%02X%02X%02X', $r, $g, $b, $a);
			return imagecolorallocatealpha($this->i, $r, $g, $b, round(0x7F - (($a * 0x7F) / 0xFF)));
		}
		
		function put($x, $y, $i) {
			if ($i instanceof Image) {
				imagecopy($this->i, $i->i, $x, $y, $i->x, $i->y, $i->w, $i->h);
			} else {
				imagesetpixel($this->i, $x, $y, $i);
			}
		}
		
		function slice($x, $y, $w, $h) {
			$i = new Image();
			$i->i = $this->i;
			list($i->x, $i->y, $i->w, $i->h) = array($x, $y, $w, $h);
			return $i;
		}
		
		function save($name, $f = self::AUTO) {
			$i = $this;
		
			if ($f == self::AUTO) {
				$f = self::PNG;
				$f = @self::$map_r[substr(strtolower(strrchr($name, '.')), 1)];
			}
			
			if ($i->x != 0 || $i->y != 0 || $i->w != imageSX($i->i) || $i->h != imageSY($i->i)) {
				$i2 = $i;
				$i = new Image($i->w, $i->h);
				$i->put(0, 0, $i2);
			}
			
			$p = array($i->i, $name);
			call_user_func_array('image' . self::$map[$f], $p);
		}
	}
?>