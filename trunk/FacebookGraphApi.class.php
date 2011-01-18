<?php

class FacebookGraphApi {
	protected $access_token = NULL;
	protected $limit, $offset;
	protected $until, $since;
	protected $q, $type;

	// http://developers.facebook.com/docs/authentication/
	protected function __construct($access_token = NULL) {
		$this->access_token = $access_token;
	}
	
	static public function fromAccessToken($access_token) {
		return new static($access_token);
	}
	
	static public function fromSecret($client_id, $client_secret) {
		return new static(static::oauth_access_token($client_id, $client_secret));
	}
	
	static public function oauth_access_token($client_id, $client_secret, $cache = false) {
		$result = file_get_contents(sprintf(
			'https://graph.facebook.com/oauth/access_token?grant_type=%s&client_id=%s&client_secret=%s',
			urlencode('client_credentials'), urlencode($client_id), urlencode($client_secret)
		));
		parse_str($result, $info);

		return $info['access_token'];
	}

	/**
	 * User will be redirected to redirect_uri + a "code" parameter with the access_token.
	 */
	static public function oauth_authorize_url($client_id, $redirect_uri, $scope = NULL, $display = NULL) {
		$info = array(
			'client_id' => $client_id,
			'redirect_uri' => $redirect_uri,
		);
		if ($info['scope'] !== NULL) $info['scope'] = $scope;
		if ($info['display'] !== NULL) {
			/*
			page  - Display a full-page authorization screen (the default)
			popup - Display a compact dialog optimized for web popup windows
			wap   - Display a WAP / mobile-optimized version of the dialog
			touch - Display an iPhone/Android/smartphone-optimized version of the dialog
			*/
			$info['display'] = $display;
		}

		return sprintf('https://graph.facebook.com/oauth/authorize?%s', http_build_query($info));
	}
	
	static public function oauth_exchange_sessions() {
		throw(new Exception("To implement"));
	}

	/**
	 *
	 */
	public function request($id, $connection_type = NULL) {
		$result = JSON::decode(file_get_contents($this->request_url($id, $connection_type)), true);
		return $result;
	}
	
	public function limit_offset($limit = NULL, $offset = NULL) {
		$that = clone $this;
		$that->limit = $limit;
		$that->offset = $offset;
		return $that;
	}
	
	public function until_since($until = NULL, $since = NULL) {
		$that = clone $this;
		$that->until = $until;
		$that->since = $since;
		return $that;
	}
	
	public function type($type = NULL) {
		$that = clone $this;
		$that->type = $type;
		return $that;
	}
	
	public function query($q = NULL) {
		$that = clone $this;
		$that->q = $q;
		return $that;
	}
	
	protected function build_query($array = array(), $add_question_mark = true) {
		foreach (array('q', 'type', 'limit', 'offset', 'until', 'since', 'access_token') as $param) {
			if ($this->$param !== NULL) $array[$param] = $this->$param;
		}
		$r = http_build_query($array);
		if ($add_question_mark) $r = ('?' . $r);
		return $r;
	}
	
	public function request_url($id, $connection_type = NULL) {
		$path = '';
		$path .= urlencode($id);
		if ($connection_type !== NULL) $path .= "/" . urlencode($connection_type);
		$path .= $this->build_query();
		return 'https://graph.facebook.com/' . $path;
	}
	
	public function search($q, $type) {
		return $this->query($q)->type($type)->request('search');
	}
}