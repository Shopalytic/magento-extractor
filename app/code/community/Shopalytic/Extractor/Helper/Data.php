<?php

class Shopalytic_Extractor_Helper_Data extends Mage_Core_Helper_Abstract {
	public function debug($msg) {
		if($this->is_debug_mode()) {
			Mage::log($msg, null, 'shopalytic.log');
		}
	}

	public function get_config($key) {
		return Mage::getStoreConfig('shopalytic_extractor/configure/' . $key, Mage::app()->getStore()->getStoreId());
	}

	public function is_debug_mode() {
		return $this->get_config('debug');
	}

	public function is_enabled() {
		return $this->get_config('enabled');
	}

	public function project_token() {
		return $this->get_config('project_token');
	}
}
