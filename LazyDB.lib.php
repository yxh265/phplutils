<?php

class LazyDB {
	private $db, $info, $user, $pass;
	
	public function __construct($info, $user, $pass) {
		$this->info = $info;
		$this->user = $user;
		$this->pass = $pass;
	}
	
	public function query($sql, $params) {
		$db_stm = $this->prepare($sql);
		$db_stm->execute($params);
		return new ArrayObject(iterator_to_array($db_stm));
	}
	
	private function __setupOnce() {
		$this->db = new PDO($this->info, $this->user, $this->pass);
		$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
	}
	
	public function __get($key) {
		$this->__setupOnce();
		return $this->db->key;
	}
	
	public function __call($name, $params) {
		$this->__setupOnce();
		return call_user_func_array(array($this->db, $name), $params);
	}
}
