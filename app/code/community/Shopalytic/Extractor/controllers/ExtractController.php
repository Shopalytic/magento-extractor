<?PHP
class Shopalytic_Extractor_ExtractController extends Mage_Core_Controller_Front_Action {
	# /shopalytic/extract/
    public function indexAction() {
		$request = Mage::app()->getRequest();
		$manifest_id = $request->getParam('manifest_id');
		$last_update = $request->getParam('last_update');
		$type = $request->getParam('type');
		$limit = $request->getParam('limit');
		$offset = $request->getParam('offset');
		$fields = explode('|', $request->getParam('fields'));

		echo $manifest_id;
		echo $last_update;


		$exporter = new Shopalytic_Extractor_Model_Exporter($last_update, $fields, $limit, $offset);
		$exporter->send('customers', $manifest_id);

		echo 'done';
    }
}

