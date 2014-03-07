<?php

class Shopalytic_Extractor_Model_ExporterBase {
	const TRACKING_URL = 'https://bulk.shopalytic.com/bulk_import/import';
	#const TRACKING_URL_DEV = 'http://requestb.in/zzw7lyzz';
	const TRACKING_URL_DEV = 'http://192.168.33.10:3000/bulk_import/import';

	protected $last_update,
		$stop_time,
		$fields,
		$limite,
		$offset,
		$errors;

	public function __construct($last_update, $stop_time, $fields, $limit = 1000, $offset = 0) {
		$this->last_update = $last_update;
		$this->stop_time = $stop_time;
		$this->fields = $fields;
		$this->limit = $limit;
		$this->offset = $offset;
		$this->errors = array();
	}

	protected function db() {
		return Mage::getSingleton('core/resource')->getConnection('core_read');
	}

	protected function error($message) {
		$this->errors[] = $message;
	}

	public function errors() {
		return $this->errors;
	}

	protected function limitor($sql) {
		if(strpos(strtolower($sql), 'where') === false) {
			$sql .= ' WHERE';
		} else {
			$sql .= ' AND';
		}

		$sql .= ' updated_at >= "' . mysql_real_escape_string($this->last_update) . '"';
		$sql .= ' AND updated_at <= "' . mysql_real_escape_string($this->stop_time) . '"';
		$sql .= ' LIMIT ' . mysql_real_escape_string($this->offset) . ',' . mysql_real_escape_string($this->limit);
		return $sql;
	}

	protected function build_select($table) {
		$sql = 'SELECT ';
		foreach($this->fields as $field) {
			$sql .= '`' . mysql_real_escape_string($field) . '`, ';
		}
		$sql = rtrim($sql, ', ');
		$sql .= 'FROM `' . mysql_real_escape_string($table) . '`';
		return $sql;
	}

	public function run($sql) {
		$conn = $this->db();
		$sql = $this->limitor($sql);
		try {
			return $conn->fetchAll($sql);
		} catch(Exception $e) {
			$this->error($e->getMessage());
			return false;
		}
	}

	public function count($method) {
		$collection = $this->{ $method . '_collection' }();
		return $collection->getSize();
	}

	public function tracking_url() {
		if(file_exists('/vagrant/SHOPALYTIC_DEV')) {
			return self::TRACKING_URL_DEV;
		}

		return self::TRACKING_URL;
	}

	public function send($method, $manifest_id, $data_format = 'json') {
		if(!$this->helper()->is_enabled()) {
			$this->error('Extension disabled');
			return false;
		}

		$csv = new Shopalytic_Extractor_Model_Csv();

		if(method_exists($this, $method)) {
			$results = $this->$method();
		} else {
			$this->error('Method "' . $method . '" not found');
			return false;
		}

		if($data_format == 'csv') {
			$processed_data = $csv->convert($results);
		} else {
			$processed_data = $this->utf8_json_encode($results);
		}

		$client = new Varien_Http_Client($this->tracking_url());
		$client->setMethod(Varien_Http_Client::POST);
		$client->setConfig(array(
			'maxredirects' => 0,
			'timeout' => 600,
		));

		$client->setParameterPost('token', $this->helper()->token());
		$client->setParameterPost('count', count($results));
		$client->setParameterPost('manifest_id', $manifest_id);
		$client->setParameterPost('data_type', $method);

		if(function_exists('gzcompress')) {
			$client->setParameterPost('data', gzcompress($processed_data));
			$client->setParameterPost('gzip', true);
		} else {
			$client->setParameterPost('data', $processed_data);
			$client->setParameterPost('gzip', false);
		}

		$response = $client->request();
		if($response->isSuccessful()) {
			return true;
		} else {
			$this->error('Failed to transfer data (response from Shopalytic): ' . $response->getRawBody());
			$this->helper()->debug($response->getRawBody());
			return false;
		}
	}

	protected function helper() {
		return Mage::helper('shopalytic_extractor');
	}

	public function valid_token($token) {
		return $this->helper()->token() == $token;
	}

	protected function utf8_json_encode($arr) {
        //convmap since 0x80 char codes so it takes all multibyte codes (above ASCII 127). So such characters are being "hidden" from normal json_encoding
        array_walk_recursive($arr, function (&$item, $key) { if (is_string($item)) $item = mb_encode_numericentity($item, array (0x80, 0xffff, 0, 0xffff), 'UTF-8'); });
        return mb_decode_numericentity(json_encode($arr), array (0x80, 0xffff, 0, 0xffff), 'UTF-8');

	}
}
