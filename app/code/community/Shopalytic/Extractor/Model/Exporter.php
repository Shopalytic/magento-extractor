<?php

class Shopalytic_Extractor_Model_Exporter {
	//const TRACKING_URL = 'https://magento.shopalytic.com';
	const TRACKING_URL = 'http://requestb.in/11b9jxc1';

	public function __construct($last_update, $fields, $limit, $offset) {
		$this->last_update = $last_update;
		$this->fields = $fields;
		$this->limit = $limit;
		$this->offset = $offset;
	}

	protected function db() {
		return Mage::getSingleton('core/resource')->getConnection('core_read');
	}

	protected function limitor($sql) {
		if(strpos(strtolower($sql), 'where') === false) {
			$sql .= ' WHERE';
		} else {
			$sql .= ' AND';
		}

		$sql .= ' updated_at >= "' . mysql_real_escape_string($this->last_update) . '"';
		$sql .= ' LIMIT ' . mysql_real_escape_string($this->offset) . ',' . mysql_real_escape_string($this->limit);
		return $sql;
	}

	public function customers() {
		return $this->run($this->build_select('customer_entity'));
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
		return $conn->fetchAll($sql);
	}

	public function send($method, $manifest_id) {
		if(!$this->helper()->is_enabled()) {
			return false;
		}

		$csv = new Shopalytic_Extractor_Model_CSV();
		$data = $csv->convert($this->$method());

		$client = new Varien_Http_Client(self::TRACKING_URL);
		$client->setMethod(Varien_Http_Client::POST);
		$client->setConfig(array(
			'maxredirects' => 0,
			'timeout' => 600,
		));

		$client->setParameterPost('project_token', $this->helper()->project_token());
		$client->setParameterPost('manifest_id', $manifest_id);

		if(function_exists('gzcompress')) {
			$client->setParameterPost('data', gzcompress($data));
			$client->setParameterPost('gzip', true);
		} else {
			$client->setParameterPost('data', $data);
			$client->setParameterPost('gzip', false);
		}

		try {
			$response = $client->request();
			if ($response->isSuccessful()) {
				echo $response->getBody();
			}
		} catch (Exception $e) {
			print_r($e);
		}
	}

	protected function helper() {
		return Mage::helper('shopalytic_extractor');
	}
}
