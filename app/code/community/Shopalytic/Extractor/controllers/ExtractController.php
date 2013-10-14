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
		$fields = explode('|', $request->getParam('fields'));

		$exporter = new Shopalytic_Extractor_Model_Exporter($last_update, $stop_time, $fields, $limit, $offset);
		if(method_exists($exporter, $type)) {
			$exporter->send($type, $manifest_id, 'json');
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

		$exporter = new Shopalytic_Extractor_Model_Exporter($last_update, $stop_time, array());
		if(method_exists($exporter, $type . '_collection')) {
			echo json_encode(array(
				'status' => '200',
				'count' => $exporter->count($type)
			));
		} else {
			$this->getResponse()->setHeader('HTTP/1.1', '400 Bad Request');
			echo json_encode(array(
				'status' => '400',
				'message' => $exporter->errors()
			));
		}
	}
}

