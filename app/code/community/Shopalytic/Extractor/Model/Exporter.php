<?php

class Shopalytic_Extractor_Model_Exporter extends Shopalytic_Extractor_Model_ExporterBase {
	public function customers_collection() {
		return Mage::getModel('customer/customer')->getCollection()
			->addAttributeToSelect('firstname')
			->addAttributeToSelect('lastname')
			->addAttributeToFilter('updated_at', array('from' => $this->last_update, 'to' => $this->stop_time));
	}

	public function customers() {
		$customers = array();

		$customer_collection = $this->customers_collection();
		$customer_collection->getSelect()->limit($this->limit, $this->offset);

		if(empty($customer_collection)) {
			return array();
		}

		foreach($customer_collection as $customer) {
			$properties = array(
				'cart_customer_id' => $customer->getId(),
				'first_name' => $customer->getFirstname(),
				'last_name' => $customer->getlastname(),
				'email' => $customer->getEmail(),
				'created_at' => $this->utc($customer->getCreatedAt()),
				'updated_at' => $this->utc($customer->getUpdatedAt()),
				'customer_group' => $this->group_name_from_id($customer->getGroupId())
			);

			$customers[] = $properties;
		}

		return $customers;
	}

	public function products_collection() {
		return Mage::getResourceModel('catalog/product_collection')
			->addAttributeToSelect('name')
			->addAttributeToSelect('cost')
			->addAttributeToSelect('url_path')
			->addAttributeToSelect('price')
			->addAttributeToFilter('updated_at', array('from' => $this->last_update, 'to' => $this->stop_time));
	}

	public function products() {
		$products = array();


		$product_collection = $this->products_collection();
		$product_collection->getSelect()->limit($this->limit, $this->offset);

		if(empty($product_collection)) {
			return array();
		}

		foreach($product_collection as $product) {
			$properties = array(
				'product_id' => $product->getId(),
				'sku' => $product->getSku(),
				'name' => $product->getName(),
				'price' => $this->money($product->getPrice()),
				'cost' => $this->money($product->getCost()),
				'url_path' => $product->getUrlPath(),
				'created_at' => $this->utc($product->getCreatedAt()),
				'updated_at' => $this->utc($product->getUpdatedAt())
			);

			$children_ids = Mage::getModel('catalog/product_type_configurable')->getChildrenIds($product->getId());
			$child_it = new RecursiveIteratorIterator(new RecursiveArrayIterator($children_ids));
			if($child_it->valid()) {
				$sub_product_ids = array();
				foreach($child_it as $child_id) {
					if($child_id != '') {
						$sub_product_ids[] = $child_id;
					}
				}

				if(!empty($sub_product_ids)) {
					$properties['sub_product_ids'] = $sub_product_ids;
				}
			}

			// Get the categories
			$product_categories = $product->getCategoryCollection()->addAttributeToSelect('name');
			if(count($product_categories)) {
				$categories = array();
				foreach($product_categories as $cat) {
					$categories[] = $cat->getName();
				}

				$properties['categories'] = $categories;
			}

			$products[] = $properties;
		}

		return $products;
	}

	public function inventory_collection() {
		return Mage::getModel('cataloginventory/stock_item')
			->getCollection()
			->addFieldToFilter('type_id', array('neq' => 'grouped'))
			->addFieldToFilter('type_id', array('neq' => 'bundle'))
			->addFieldToFilter(
				array('attribute' => 'manage_stock', 'eq' => 1),
				array('attribute' => 'use_config_manage_stock', 'eq' => 1)
			);
	}

	public function inventory() {
		$items = array();


		$inventory_collection = $this->inventory_collection();
		$inventory_collection->getSelect()->limit($this->limit, $this->offset);

		if(empty($inventory_collection)) {
			return array();
		}

		foreach($inventory_collection as $item) {
			$properties = array(
				'product_id' => $item->getId(),
				'backorders' => round($item->getBackorders()),
				'qty' => round($item->getQty()),
				'is_in_stock' => $item->getIsInStock() ? 'true' : 'false',

			);

			$items[] = $properties;
		}

		return $items;
	}

	public function orders_collection() {
		return Mage::getModel('sales/order')->getCollection()
			->addAttributeToFilter('updated_at', array('from' => $this->last_update, 'to' => $this->stop_time));
	}

	public function orders() {
		$orders = array();

		$orders_collection = $this->orders_collection();
		$orders_collection->getSelect()->limit($this->limit, $this->offset);

		if(empty($orders_collection)) {
			return array();
		}

		foreach($orders_collection as $order) {
			switch($order->getState()) {
				case Mage_Sales_Model_Order::STATE_CANCELED:
					$order_status = 'void';
					break;
				default:
                    $order_status = 'closed';
					break;
			}

			$properties = array(
				'order_id' => $order->getIncrementId(),
				'cart_id' => $order->getQuoteId(),
				'status' => $order_status,
				'order_date' => $this->utc($order->getCreatedAt()),
				'updated_at' => $this->utc($order->getUpdatedAt()),
				'shipping_description' => $order->getShippingDescription(),
				'shipping_method' => $order->getShippingMethod(),
				'guest' => $order->getCustomerIsGuest(),
				'total_qty' => (int) $order->getTotalQtyOrdered(),
				'cart_customer_id' => $order->getCustomerId(),
				'email' => $order->getCustomerEmail(),
				'customer_first_name' => $order->getCustomerFirstname(),
				'customer_last_name' => $order->getCustomerLastname(),
				'qty_ordered' => (int) $order->getTotalQtyOrdered(),
				'total' => $this->money($order->getGrandTotal() - $order->getTotalRefunded()),
				'subtotal' => $this->money($order->getSubtotal() - $order->getSubtotalRefunded()),
				'total_shipping' => $this->money($order->getShippingAmount()),
				'total_tax' => $this->money($order->getTaxAmount()),
				'total_discount' => $this->money(abs($order->getDiscountAmount())),
				'total_refund' => $this->money($order->getTotalRefunded()),
				'refunded_discount' => $this->money($order->getDiscountRefunded()),
				'refunded_shipping' => $this->money($order->getShippingRefunded()),
				'refunded_tax' => $this->money($order->getTaxRefunded()),
				'customer_group' => $this->group_name_from_id($order->getCustomerGroupId())
			);

			$shipping = $order->getShippingAddress();
			if($shipping) {
				$properties['shipping'] = array(
					'city' => $shipping->getCity(),
					'zipcode' => $shipping->getPostcode(),
					'country' => Mage::getModel('directory/country')->load($shipping->getCountryId())->getIso3Code()
				);
			}

			$billing = $order->getBillingAddress();
			if($billing) {
				$properties['billing'] = array(
					'city' => $billing->getCity(),
					'zipcode' => $billing->getPostcode(),
					'country' => Mage::getModel('directory/country')->load($billing->getCountryId())->getIso3Code()
				);
			}

			$payment = $order->getPayment();
			if($payment->getMethod() || $payment->getCcType()) {
				$properties['payment_type'] = array(
					'type' => $payment->getMethod(),
					'cc_type' => $payment->getCcType()
				);
			}

			// Discount
			if($order->getCouponCode()) {
				$properties['coupons'] = array();
				$properties['coupons'][] = array(
					'code' => $order->getCouponCode(),
					'value' => $this->money(abs($order->getDiscountAmount()))
				);
			}

			$discount_rules = $this->discount_rules($order->getAppliedRuleIds());
			if($discount_rules) {
				$properties['discount_rules'] = $discount_rules;
			}

			if($order->getGiftCardsAmount() > 0) {
				$properties['total_giftcard'] = $this->money($order->getGiftCardsAmount());
			}

			// Get all the sub product sub product ids
			$sub_products = array();
			foreach($order->getAllItems() as $item) {
				if($item->getParentItemId()) {
					$sub_products[$item->getParentItemId()][] = $item->getProductId();
				}
			}

			// Line items
			$items = $order->getAllVisibleItems();
			if($items) {
				$properties['line_items'] = array();
				foreach ($items as $item) {
					$line = array(
						'sku' => $item['sku'],
						'product_id' => $item['product_id'],
						'qty_ordered' => (int) $item['qty_ordered'],
						'qty_refunded' => (int) $item['qty_refunded'],
						'price' => $this->money($item['price']),
						'amount_refunded' => $this->money($item['amount_refunded']),
						'cost' => $this->money($item['base_cost']),
						'discount' => $this->money($item['discount_amount']),
						'discount_refunded' => $this->money($item['discount_refunded'])
					);

					$discount_rules = $this->discount_rules($item->getAppliedRuleIds());
					if($discount_rules) {
						$line['discount_rules'] = $discount_rules;
					}

					// Add the bundled sub products if there are any
					if(isset($sub_products[$item->getItemId()])) {
						$line['sub_product_ids'] = $sub_products[$item->getItemId()];
					}

					$product_options = $item->getProductOptions();

					// Item attributes
					$attributes = $this->product_attributes($product_options);
					if($attributes) {
						$line['attributes'] = $attributes;
					}

					// Item options
					$options = $this->product_options($product_options);
					if($options) {
						$line['options'] = $options;
					}

					$properties['line_items'][] = $line;
				}
			}

			$tracking_numbers = Mage::getResourceModel('sales/order_shipment_track_collection')
	->setOrderFilter($order);
			if($tracking_numbers) {
				$properties['shipments'] = array();
				foreach($tracking_numbers as $track) {
					$properties['shipments'][] = array(
						'carrier' => $track->getCarrierCode(),
						'tracking_number' => $track->getTrackNumber(),
						'date_shipped' => $this->utc($track->getCreatedAt())
					);
				}
			}

			$orders[] = $properties;
		}

		return $orders;
	}

	public function carts_collection() {
		return Mage::getModel('sales/quote')->getCollection()
			->addFieldToFilter('updated_at', array('from' => $this->last_update, 'to' => $this->stop_time));
	}

	public function group_name_from_id($id) {
		$cust_group = Mage::getModel('customer/group')->load($id);
		return $cust_group->getCode();
	}

	public function product_attributes($attrs) {
		$attributes = array();
		if(!empty($attrs['attributes_info'])) {
			foreach($attrs['attributes_info'] as $attribute) {
				$attributes[] = array(
					'name' => $attribute['label'],
					'value' => $attribute['value'],
				);
			}
		}

		return $attributes;
	}

	public function product_options($opts) {
		$options = array();
		if(!empty($opts['options'])) {
			foreach($opts['options'] as $option) {
				$options[] = array(
					'name' => $option['label'],
					'value' => $option['value'],
				);
			}
		}

		return $options;
	}

	public function discount_rules($rule_string) {
		if($rule_string == '') {
			return array();
		}

		$rules = array();
		foreach(explode(',', $rule_string) as $rule_id) {
			$rule = Mage::getModel('catalogrule/rule')->load($rule_id);
			$rules[] = $rule->getName();
		}

		return $rules;
	}

	public function carts() {
		$quote_collection = $this->carts_collection();
		$quote_collection->getSelect()->limit($this->limit, $this->offset);

		if(empty($quote_collection)) {
			return array();
		}

		foreach($quote_collection as $quote) {
			$totals = $quote->getTotals();

			$shipping = $quote->getShippingAddress();
			$billing = $quote->getBillingAddress();

			$properties = array(
				'cart_id' => $quote->getId(),
				'created_at' => $this->utc($quote->getCreatedAt()),
				'updated_at' => $this->utc($quote->getUpdatedAt()),
				'guest' => $quote->getCustomerIsGuest(),
				'cart_customer_id' => $quote->getCustomerId(),
				'email' => $quote->getCustomerEmail(),
				'customer_first_name' => $quote->getCustomerFirstname(),
				'customer_last_name' => $quote->getCustomerLastname(),
				'total' => $this->money($quote->getGrandTotal()),
				'subtotal' => $this->money($quote->getSubtotal()),
				'customer_group' => $this->group_name_from_id($quote->getCustomerGroupId())
			);


			$payment = $quote->getPayment();
			if($payment->getMethod() || $payment->getCcType()) {
				$properties['payment_type'] = array(
					'type' => $payment->getMethod(),
					'cc_type' => $payment->getCcType()
				);
			}

			if($shipping->city || $shipping->zipcode || $shipping->country) {
				$properties['total_shipping'] = $this->money($shipping->getShipping_amount());

				$properties['shipping'] = array(
					'city' => $shipping->getCity(),
					'zipcode' => $shipping->getPostcode(),
					'country' => Mage::getModel('directory/country')->load($shipping->getCountryId())->getIso3Code()
				);
			}

			if($billing->city || $billing->zipcode || $billing->country) {
				$properties['billing'] = array(
					'city' => $billing->getCity(),
					'zipcode' => $billing->getPostcode(),
					'country' => Mage::getModel('directory/country')->load($billing->getCountryId())->getIso3Code()
				);
			}

			// Discount
			if(isset($totals['discount'])) {
				$properties['coupons'] = array();
				$properties['coupons'][] = array(
					'code' => $quote->getCouponCode(),
					'value' => $this->money(abs($totals['discount']->getValue()))
				);
			}

			if(isset($totals['tax'])) {
				$properties['total_tax'] = $this->money($totals['tax']->getValue());
			}

			// Get all the sub product sub product ids
			$sub_products = array();
			foreach($quote->getAllItems() as $item) {
				if($item->getParentItemId()) {
					$sub_products[$item->getParentItemId()][] = $item->getProductId();
				}
			}

			$discount_rules = $this->discount_rules($quote->getAppliedRuleIds());
			if($discount_rules) {
				$properties['discount_rules'] = $discount_rules;
			}

			// Line items
			$items = $quote->getAllVisibleItems();
			if($items) {
				$properties['line_items'] = array();
				foreach ($items as $item) {
					$line = array(
						'sku' => $item['sku'],
						'product_id' => $item['product_id'],
						'price' => $this->money($item['price']),
						'cost' => $this->money($item['base_cost']),
						'qty' => (int) $item['qty'],
						'discount' => $this->money($item['discount_amount']),
					);

					// Add the bundled sub products if there are any
					if(isset($sub_products[$item->getItemId()])) {
						$line['sub_product_ids'] = $sub_products[$item->getItemId()];
					}

					$discount_rules = $this->discount_rules($item->getAppliedRuleIds());
					if($discount_rules) {
						$line['discount_rules'] = $discount_rules;
					}

					$product_options = $item->getProduct()->getTypeInstance(true)->getOrderOptions($item->getProduct());

					// Item attributes
					$attributes = $this->product_attributes($product_options);
					if($attributes) {
						$line['attributes'] = $attributes;
					}

					// Item options
					$options = $this->product_options($product_options);
					if($options) {
						$line['options'] = $options;
					}

					$properties['line_items'][] = $line;
				}
			}

			$quotes[] = $properties;
		}

		return $quotes;
	}

	private function money($amt) {
		return round($amt, 2) * 100;
	}

	private function utc($time) {
		return date('c', strtotime($time));
	}
}
