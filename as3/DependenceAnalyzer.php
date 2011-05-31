<?php

class TokenParser {
	protected $tokens;
	protected $position;
	protected $tokenCount;

	public function __construct($contents) {
		$this->tokens = array_map(function($v) { 
			if (is_array($v)) $v = $v[1];
			if (preg_match('@^\\s+$@', $v)) {
				if (strpos($v, "\n") !== false) {
					$v = "\n";
				} else {
					$v = " ";
				}
			}
			return $v;
		}, token_get_all('<?php ' . $contents));
		$this->position = 0;
		$this->tokenCount = count($this->tokens);
	}
	
	public function count() {
		return count($this->tokens);
	}
	
	public function hasMore() {
		//printf("%d,%d\n", $this->position, $this->count());
		return $this->position < $this->tokenCount;
	}
	
	public function nextNotEmpty() {
		while ($this->hasMore()) {
			$cur = $this->next();
			if (trim($cur) != '') return $cur;
		}
		return null;
	}
	
	public function next() {
		$this->position++;
		return $this->current();
	}
	
	public function current() {
		$ref = &$this->tokens[$this->position];
		//echo "$this->position\n";
		if (!isset($ref)) {
			//throw(new Exception("Out of limits"));
			return false;
		}
		return $ref;
	}
	
	public function getTokensUpTo($upToToken) {
		if (!is_array($upToToken)) $upToToken = array($upToToken);
		//print_r($upToToken);
		$rtokens = array();
		for (;$this->hasMore() && !in_array($this->current(), $upToToken); $this->next()) {
			//echo "'" . $this->current() . "'\n";
			$rtokens[] = $this->current();
		}
		return $rtokens;
	}
}

class As3Project {
	public $files = array();
	public $packages = array();
	
	public function __construct() {
	}
	
	public function addFile(As3File $file) {
		$this->files[] = $file;
		$this->packages += $file->packages;
	}
	
	public function expandImports() {
		foreach ($this->files as $file) {
			$file->expandImports($this);
		}
	}
	
	public function getPackageFromPath($packagePath) {
		$package = &$this->packages[$packagePath];
		if (!isset($package)) {
			$package = new As3Package($packagePath);
		}
		return $package;
	}
	
	public function dumpDependencies($level, $includes) {
		//print_r($this->packages);
		foreach ($this->packages as $name => $package) {
			//echo "::$name\n";
			if ($package === null) continue;
			$package->dumpDependencies($level, $includes);
		}
	}
}

class As3File {
	public $path;
	public $packages = array();
	public $classes = array();
	
	public function __construct($path) {
		$this->path = $path;
	}
	
	public function addClass(As3PackageObject $class) {
		$this->classes[] = $class;
		$this->packages[$class->package->path] = $class->package;
		return $class;
	}
	
	public function expandImports(As3Project $project) {
		foreach ($this->classes as $class) {
			$class->expandImports($project);
		}
	}
}

function getPackageLevel($packageName, $level) {
	$parts = explode('.', $packageName);
	switch ($parts[0]) {
		case 'it': case 'com': case 'org': case 'net': case 'nl': case 'pl': case 'fl': case 'de': case 'br': case 'mx': case 'gui':
			$level++;
			if ($parts[0] == 'br' && $parts[1] == 'com') {
				$level++;
			}
		break;
	}
	return implode('.', array_slice($parts, 0, $level));
}

function in_array_part_start($string, $parts, $globalLevel) {
	if (!empty($parts)) {
		foreach ($parts as $part) {
			$level = null;
			@list($ppart, $level) = explode(':', $part);
			if (strpos($string, $ppart) === 0) {
				return $level ? $level : $globalLevel;
			}
		}
		return false;
	} else {
		return $globalLevel;
	}
}

class As3Package {
	public $path;
	public $classes = array();
	public $interfaces = array();
	
	public function __construct($path) {
		$this->path = $path;
	}
	
	public function getClassesAndInterfaces() {
		return $this->classes + $this->interfaces;
	}
	
	public function addClassByName($className) {
		return $this->classes[$className] = new As3Class($this, $className);
	}
	
	public function addInterfaceByName($interfaceName) {
		return $this->interfaces[$interfaceName] = new As3Interface($this, $interfaceName);
	}
	
	public function addObjectByClassAndName($type, $className) {
		switch ($type) {
			case 'interface': return $this->addInterfaceByName($className);
			case 'class'    : return $this->addClassByName($className);
			default: throw(new Exception("Invalid type '{$type}'"));
		}
	}
	
	public function dumpDependencies($level, $includes) {
		$packageImports = array();
		foreach ($this->getClassesAndInterfaces() as $class) {
			$packageImports = array_merge($packageImports, $class->imports->getPackageImports());
		}
		$packageImports = array_unique($packageImports);

		if ($level == 0) {
			printf("subgraph \"%s\" {\n", $this->path);
			foreach ($this->getClassesAndInterfaces() as $class) {
				//echo "$class\n";
				//printf(":::%s\n", $class->getFullClassPath());
				//if (isset($class))
				$class->dumpDependencies($level, $includes);
			}
			printf("}\n");
		} else {
			//printf("subgraph \"%s\" {\n", $this->path);
			$componentLevel = $level;
		
			static $pairs = array();
			foreach ($packageImports as $packageImport) {
				if (false === ($level1 = in_array_part_start($packageImport, $includes, $componentLevel))) continue;
				if (false === ($level2 = in_array_part_start($this->path   , $includes, $componentLevel))) continue;
				
				$packageImport = getPackageLevel($packageImport, $level1);
				$package       = getPackageLevel($this->path, $level2);
				
				$pair = sprintf("\"%s\"", $package);

				if ($packageImport != $package) {
					//$pair = sprintf("\"%s\" -> \"%s\"", $packageImport, $package);
					$pair = sprintf("\"%s\" -> \"%s\"", $package, $packageImport);
					if (!isset($pairs[$pair])) {
						$pairs[$pair] = true;
						echo "{$pair}\n";
					}
				}
			}
			//printf("}\n");
		}
	}
	
	public function __toString() {
		return "As3Package('{$this->path}')";
	}
}

class As3Import {
	public $packagePath;
	public $className;
	
	public function __construct($path) {
		$pos = strrpos($path, '.');
		$this->packagePath = substr($path, 0, $pos);
		$this->className   = substr($path, $pos + 1);
	}
	
	public function getCompletePath() {
		return ltrim("{$this->packagePath}.{$this->className}", '.');
	}
	
	public function __toString() {
		return "As3Import('{$this->packagePath}'.'{$this->className}')";
	}
}

class As3Imports {
	public $class;
	public $imports = array();
	
	public function __construct() {
	}
	
	public function addImport(As3Import $import) {
		$this->imports[] = $import;
	}
	
	public function getPackageImports() {
		$packages = array();
		foreach ($this->imports as $import) {
			$packages[] = $import->packagePath;
		}
		return array_unique($packages);
	}
	
	public function expandImports(As3Project $project) {
		$newImports = array();
		foreach ($this->imports as $import) {
			// Expand classes
			if ($import->className == '*') {
				$package = &$project->packages[$import->packagePath];
				if (isset($package)) {
					foreach ($package->classes as $class) {
						$newImports[] = new As3Import($class->getFullClassPath());
					}
				}
			} else {
				$newImports[] = $import;
			}
		}
		$this->imports = $newImports;
		$this->cleanImports($project);
	}
	
	public function cleanImports(As3Project $project) {
		$newImports = array();
		foreach ($this->imports as $import) {
			if ($import->packagePath != $this->class->package) {
				$newImports[] = $import;
			}
		}
		$this->imports = $newImports;
	}

	public function dumpDependencies($classParent, $level, $includes) {
		//print_r($this->imports);
		foreach ($this->imports as $import) {
			if ($import->getCompletePath() != $classParent) {
				printf("\"%s\" -> \"%s\"\n", $import->getCompletePath(), $classParent);
			}
		}
	}
}

class As3PackageObject {
	/**
	 * @var As3Package
	 */
	public $package;
	
	/**
	 * @var String
	 */
	public $className;

	/**
	 * @var String
	 */
	public $parentClassName;

	public $imports;
	
	/**
	 * @var Array<String>
	 */
	public $implementingInterfacesNames = array();
	
	/**
	 * @var Array<As3Class>
	 */
	public $classReferences = array();
	
	public function __construct(As3Package $package, $className) {
		$this->package   = $package;
		$this->className = $className;
	}

	public function addImplementingClassName($interfaceName) {
		$this->implementingInterfacesNames[] = $interfaceName;
	}
	
	public function setParentClassName($parentClassName) {
		$this->parentClassName = $parentClassName;
	}
	
	public function setImports(As3Imports $imports) {
		$this->imports  = $imports;
	}
	
	public function getFullClassPath() {
		return ltrim($this->package->path . '.' . $this->className, '.');
	}
	
	public function expandImports(As3Project $project) {
		//echo "$this->className\n";
		if (isset($this->imports)) $this->imports->expandImports($project);
	}

	public function __toString() {
		return "As3PackageObject('" . $this->getFullClassPath() . "')";
	}
	
	public function dumpDependencies($level, $includes) {
		//echo "::::" . $this->getFullClassPath() . "\n";
		printf("\"%s\"\n", $this->getFullClassPath());
		//if (isset($this->imports))
		$this->imports->dumpDependencies($this->getFullClassPath(), $level, $includes);
	}
}

class As3Interface extends As3PackageObject {
	public function __toString() {
		return "As3Interface('" . $this->getFullClassPath() . "')";
	}
}

class As3Class extends As3PackageObject {
	public function __toString() {
		return "As3Class('" . $this->getFullClassPath() . "')";
	}
}

class DependenceAnalyzer {
	public $sourcesFolders = array();
	public $analyzedFiles = array();
	/**
	 * @var As3Project
	 */
	public $project;
	
	public function __construct() {
		$this->project = new As3Project();
	}

	public function addSourceFolder($sourceFolder) {
		$this->sourcesFolders[] = $sourceFolder;
	}
	
	public function analyzeAllSourceFolders() {
		foreach ($this->sourcesFolders as $sourceFolder) {
			$this->analyzeSourceFolder($sourceFolder);
		}
	}
	
	public function analyzeSourceFolder($sourceFolder) {
		foreach (scandir($sourceFolder) as $file) {
			$rfile = "{$sourceFolder}/{$file}";
			if ($file[0] == '.') continue;
			//echo "$rfile\n";
			if (is_dir($rfile)) {
				$this->analyzeSourceFolder($rfile);
			} else {
				if (preg_match('@\\.as@', $file)) {
					$this->analyzeAs3Once(realpath($rfile));
				}
			}
		}
	}
	
	public function analyzeAs3Once($fileName) {
		//echo "{$fileName}\n";
		$analyzedFile = &$this->analyzedFiles[$fileName];

		if (!isset($analyzedFile)) {
			$analyzedFile = $this->analyzeAs3($fileName);
		}

		return $analyzedFile;
	}
	
	/*
	public function package($tokenParser) {
	}

	public function parseFile($tokenParser) {
	}
	*/

	public function analyzeAs3($fileName) {
		$as3File = new As3File($fileName);
		
		$contents = file_get_contents($fileName);
		
		if (substr($contents, 0, 3) == "\xEF\xBB\xBF") {
			$contents = substr($contents, 3);
		}
		
		$currentClass = null;
		$currentPackage = null;
		$imports = new As3Imports();
		$bracketCount = 0;
		for ($tokenParser = new TokenParser($contents); $tokenParser->hasMore(); $tokenParser->next()) {
			switch ($tokenParser->current()) {
				case '{':
					$bracketCount++;
					//echo "+1\n";
				break;
				case '}':
					$bracketCount--;
					//echo "-1\n";
					
					if ($bracketCount == 0) {
						$imports = new As3Imports();
					}
				break;
				case 'package':
					$tokenParser->nextNotEmpty();
					$currentPackage = $this->project->getPackageFromPath(trim(implode('', $tokenParser->getTokensUpTo('{'))));
					//$as3File->addPackage($currentPackage);
					//echo "{$currentPackage}\n";
					$bracketCount++;
					//echo "+1\n";
				break;
				case 'import':
					$tokenParser->nextNotEmpty();
					$currentImport = new As3Import(trim(implode('', $tokenParser->getTokensUpTo(array(';', "\n")))));
					$imports->addImport($currentImport);
					//echo "{$currentImport}\n";
				break;
				case 'interface':
				case 'class':
					$type = $tokenParser->current();
					$currentClass = $currentPackage->addObjectByClassAndName($type, $tokenParser->nextNotEmpty());
					//print_r($currentPackage);
					$as3File->addClass($currentClass);
					$imports->class = $currentClass;
					$currentClass->setImports($imports);
					$implementing = false;
					$alreadyExtended = false;
					while ($tokenParser->hasMore()) {
						switch ($tokenParser->nextNotEmpty()) {
							case 'extends':
								if ($type == 'interface') {
									$currentClass->addImplementingClassName($tokenParser->nextNotEmpty());
									$implementing = true;
								} else {
									if ($alreadyExtended) throw(new Exception("Invalid"));
									$currentClass->setParentClassName($tokenParser->nextNotEmpty());
									$alreadyExtended = true;
								}
							break;
							case 'implements':
								$currentClass->addImplementingClassName($tokenParser->nextNotEmpty());
								$implementing = true;
							break;
							case ',':
								if ($type == 'class' && !$implementing) throw(new Exception("Invalid"));
								$currentClass->addImplementingClassName($tokenParser->nextNotEmpty());
							break;
							case '{':
								$bracketCount++;
								//echo "+1\n";
							break 2;
						}
					}
					//echo "{$currentClass}\n";
				break;
			}
		}
		
		//if ($bracketCount != 0) throw(new Exception("Bracket mismatch bracketCount:{$bracketCount} for '{$fileName}'"));		

		$this->project->addFile($as3File);
		
		return $as3File;
	}
	
	public function finalize() {
		$this->project->expandImports();
	}
}

function showHelp() {
	printf("DependenceAnalyzer\n");
	printf("\n");
	printf("--level=[1,2,3,4...]\n");
	printf("--include=fl.utils\n");
	printf("--include=fl:3\n");
	printf("--source=c:/my/as3/files\n");
	exit(-1);
}

$includes = array();
$sources = array();
$level = null;

foreach (array_slice($argv, 1) as $arg) {
	if (substr($arg, 0, 2) == '--') {
		list($name, $value) = explode('=', substr($arg, 2), 2);
		//echo "$name = $value\n";
		switch ($name) {
			case 'level':
				$level = (int)$value;
			break;
			case 'include':
				$includes[] = $value;
			break;
			case 'source':
				$sources[] = $value;
			break;
			case 'help':
				showHelp();
			break;
			default:
				die("Unknown arg: {$arg}\n");
			break;
		}
	} else {
		die("Unknown arg: {$arg}\n");
	}
	//echo "$arg\n";
}

if (($level === null) && empty($includes)) {
	showHelp();
}

$sources_hash = md5(implode(',', $sources));
$dependenceAnalyzer = @unserialize(file_get_contents($serialize_file = "{$sources_hash}.serialize"));

if (!($dependenceAnalyzer instanceof DependenceAnalyzer)) {
	$dependenceAnalyzer = new DependenceAnalyzer();
	foreach ($sources as $source) {
		$dependenceAnalyzer->addSourceFolder($source);
	}
	$dependenceAnalyzer->analyzeAllSourceFolders();
	$dependenceAnalyzer->finalize();

	file_put_contents($serialize_file, serialize($dependenceAnalyzer));
}

ob_start();
echo "digraph G {\n";
$dependenceAnalyzer->project->dumpDependencies($level, $includes);
echo "}\n";
$graphContents = ob_get_clean();
file_put_contents('graph.dot', $graphContents);
@unlink('graph.svg');

//echo `twopi graph.dot -Goverlap=false -Tsvg -o graph.svg`;
echo `dot graph.dot -Goverlap=false -Tsvg -o graph.svg`;
