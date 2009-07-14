<?php
class Jar extends ZipArchive
{
	protected $name;
	protected $size;
	
	public function __construct( $name )
	{
		$this->name = $name;
		$this->size = filesize( $name );
		$this->open( $name, 0 );
	}
	
	public function getManifest() {
		return fread( $this->getStream( 'META-INF/MANIFEST.MF' ), 0x10000 );
	}
	
	public function createJad() {
		return (
			"MIDlet-Jar-Size: {$this->size}\n" . 
			"MIDlet-Jar-URL: {$this->name}\n" .
			$this->getManifest()
		);
	}
}

/*
$jar = new Jar('WebViewer.jar.zip');
echo $jar->createJad();
*/
?>