<?php

class Shopalytic_Extractor_Helper_Data extends Mage_Core_Helper_Abstract {
	const TRACKING_URL = 'track.shopalytic.com';
	const TRACKING_PATH = 'pixel.gif';

	public function debug($msg) {
		if($this->is_debug_mode()) {
			Mage::log($msg, null, 'shopalytic.log');
		}
	}

	public function get_config($key) {
		return Mage::getStoreConfig('shopalytic_tracker/configure/' . $key, Mage::app()->getStore()->getStoreId());
	}

	public function is_debug_mode() {
		return $this->get_config('debug');
	}

	public function is_enabled() {
		return $this->get_config('enabled');
	}

	public function is_test_mode() {
		return $this->get_config('test');
	}

	public function delivery_method() {
		return $this->get_config('delivery_method');
	}

	public function api_key() {
		return $this->get_config('api_key');
	}

	public function track($action, $properties = array()) {
		$this->debug("$action: " . json_encode($properties));

		if($this->is_test_mode()) {
			return;
		}

		$visitor = Shopalytic_Tracker_Model_Visitor::instance()->visitor_cookie();
		$session = json_decode(Mage::getModel('core/cookie')->get('sa_session'));

		// We don't have proper cookies, bail
		if(!$visitor && !$session) {
			return;
		}

		$properties['action'] = $action;
		$properties['project_token'] = $this->api_key();
		$properties['platform'] = 'magento';
		$properties['session_id'] = $session->session_id;
		$properties['ip'] = Mage::helper('core/http')->getRemoteAddr();
		$properties['user_agent'] = Mage::helper('core/http')->getHttpUserAgent();

		if(property_exists($session, 'referrer')) {
			$properties['session_referrer'] = $session->referrer;
		}

		if(property_exists($visitor, 'referrer')) {
			$properties['initial_referrer'] = $visitor->referrer;
		}

		if(property_exists($session, 'url')) {
			$properties['session_url'] = $session->url;
		}

		if(property_exists($visitor, 'url')) {
			$properties['initial_url'] = $visitor->url;
		}

		if(property_exists($visitor, 'alias')) { // has identified
			$properties['alias'] = $visitor->alias;
		} else { // has not identified
			$properties['distinct_id'] = $visitor->distinct_id;
		}

		$params = array(
			'data' => base64_encode(json_encode($properties)),
			't' => gmdate('U'),
			'd' => $this->delivery_method()
		);

		switch($this->delivery_method()) {
			case 'asyc':
				$this->async_send($params);
				break;
			default:
				$this->sync_send($params);
				break;
		}
	}

	public function async_send($params) {
		$fp = fsockopen('ssl://' . self::TRACKING_URL, 443, $errno, $errstr, 5);
		if($fp !== false) {
			$path = self::TRACKING_PATH . '?' . http_build_query($params);

			$out  = "GET $path HTTP/1.1\r\n";
			$out .= "Host: " . self::TRACKING_PATH . "\r\n";
			$out .= "Connection: Close\r\n\r\n";

			fwrite($fp, $out);
			fclose($fp);
		} else {
			$this->debug('async_send: failed to connect');
		}
	}

	public function sync_send($params) {
		$url = 'https://' . self::TRACKING_URL . '/' . self::TRACKING_PATH . '?' . http_build_query($params);
		$client = new Varien_Http_Client($url);
		$client->setMethod(Varien_Http_Client::GET);
		$client->setConfig(array(
			'maxredirects' => 0,
			'timeout' => 5,
        ));

		try {
			$response = $client->request();
			if(!$response->isSuccessful()) {
				$this->debug('sync_send: failed to send');
			}
		} catch (Exception $e) {
			$this->debug('sync_send: failed to connect');
		}
	}


    public function product_categories($product) {
        $id = current($product->getCategoryIds());
        $category = Mage::getModel('catalog/category')->load($id);
        $aCategories = array();
        foreach ($category->getPathIds() as $k => $id) {
            // Skip null and root
            if ($k > 1) {
                $category = Mage::getModel('catalog/category')->load($id);
                $aCategories[] = $this->toUTF8($category->getName());
            }
        }
        return join('/', $aCategories);
    }
}
