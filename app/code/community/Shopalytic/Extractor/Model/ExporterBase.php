<?php

class Shopalytic_Extractor_Model_ExporterBase {
	const TRACKING_URL = 'https://bulk.shopalytic.com';
	const TRACKING_URL_DEV = 'http://requestb.in/zzw7lyzz';

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
		return $collection->count();
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

		$csv = new Shopalytic_Extractor_Model_CSV();

		$results = $this->$method();
		if(!$results) {
			$this->error('Method "' . $method . '" not found');
			return false;
		}

		if($data_format == 'csv') {
			$processed_data = $csv->convert($results);
		} else {
			$processed_data = json_encode($results);
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

		if(function_exists('gzcompress')) {
			$client->setParameterPost('data', gzcompress($processed_data));
			$client->setParameterPost('gzip', true);
		} else {
			$client->setParameterPost('data', $processed_data);
			$client->setParameterPost('gzip', false);
		}

		try {
			$response = $client->request();
			if($response->isSuccessful()) {
				return true;
			} else {
				$msg = 'Failed to transfer data: ' . $response->getMessage();
				$this->error($msg);
				$this->helper()->debug($msg);
				return false;
			}

		} catch(Exception $e) {
			$msg = 'Failed to transfer data';
			$this->error($msg);
			$this->helper()->debug($msg);
			return false;
		}
	}

	protected function helper() {
		return Mage::helper('shopalytic_extractor');
	}

	public function valid_token($token) {
		return md5($this->helper()->token() . '_shopalytic') == $token;
	}
}
