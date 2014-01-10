<?PHP
class Shopalytic_Extractor_ExtractController extends Mage_Core_Controller_Front_Action {
	# /shopalytic/extract/
    public function transferAction() {
		$request = Mage::app()->getRequest();
		$manifest_id = $request->getParam('manifest_id');
		$last_update = $request->getParam('last_update');
		$stop_time = $request->getParam('stop_time');
		$type = $request->getParam('type');
		$limit = $request->getParam('limit');
		$offset = $request->getParam('offset');
		$token = $request->getParam('token');
		$fields = explode('|', $request->getParam('fields'));

		$exporter = new Shopalytic_Extractor_Model_Exporter($last_update, $stop_time, $fields, $limit, $offset);
		if(!$exporter->valid_token($token)) {
			echo json_encode(array(
				'status' => '403',
				'message' => 'Invalid token'
			));
			die();
		}

		if(method_exists($exporter, $type) && $exporter->send($type, $manifest_id, 'json')) {
			echo json_encode(array(
				'status' => '200'
			));
		} else {
			$this->getResponse()->setHeader('HTTP/1.1', '400 Bad Request');
			echo json_encode(array(
				'status' => '400',
				'message' => $exporter->errors()
			));
		}
    }

	# /shopaltyic/manifest
	public function manifestAction() {
		$request = Mage::app()->getRequest();
		$last_update = $request->getParam('last_update');
		$stop_time = $request->getParam('stop_time');
		$type = $request->getParam('type');
		$token = $request->getParam('token');

		$exporter = new Shopalytic_Extractor_Model_Exporter($last_update, $stop_time, array());

		if(!$exporter->valid_token($token)) {
			echo json_encode(array(
				'status' => '403',
				'message' => 'Invalid token'
			));
		} else {
			echo json_encode(array(
				'status' => '200',
				'counts' => array(
					'customers' => $exporter->count('customers'),
					'orders' => $exporter->count('orders'),
					'carts' => $exporter->count('carts'),
					'products' => $exporter->count('products'),
				)
			));
		}
	}

	# /shopalytic/initialize
	public function initializeAction() {
		$helper = Mage::helper('shopalytic_extractor');
		$request = Mage::app()->getRequest();
		$web = Mage::getStoreConfig('web');
		$token = $request->getParam('token');

		if($helper->token()) {
			echo json_encode(array(
				'status' => '403',
				'code' => '100',
				'message' => 'Website has already been registered to another Shopalytic site'
			));
		} elseif(!$token) {
			echo json_encode(array(
				'status' => '403',
				'code' => '101',
				'message' => 'Install token is invalid'
			));
		} else {
			// Save the token
			$helper->set_token($token);
			$store = Mage::app()->getStore();

			echo json_encode(array(
				'status' => '200',
				'config' => array(
					'url' => $web['secure']['base_url'],
					'name' => $store->getFrontendName(),
					'token' => $helper->token(),
					'timezone' => $store->getConfig('general/locale/timezone'),
					'currency_code' => Mage::app()->getStore()->getCurrentCurrencyCode()
				)
			));
		}
	}
}
