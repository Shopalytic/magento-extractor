<?php

class Shopalytic_Extractor_Helper_Data extends Mage_Core_Helper_Abstract {
	const CONFIG_PATH = 'shopalytic_extractor/configure/';

	public function debug($msg) {
		if($this->is_debug_mode()) {
			Mage::log($msg, null, 'shopalytic.log');
		}
	}

	public function get_config($key) {
		return Mage::getStoreConfig(self::CONFIG_PATH . $key, Mage::app()->getStore()->getStoreId());
	}

	public function is_debug_mode() {
		return $this->get_config('debug');
	}

	public function is_enabled() {
		return $this->get_config('enabled');
	}

	public function token() {
		return $this->get_config('token');
	}

	public function set_token($token) {
		Mage::getModel('core/config')->saveConfig(self::CONFIG_PATH . 'token', $token);
		Mage::getConfig()->reinit();
		Mage::app()->reinitStores();
	}
}
