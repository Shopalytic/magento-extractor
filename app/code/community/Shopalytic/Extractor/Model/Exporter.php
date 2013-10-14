<?php

class Shopalytic_Extractor_Model_Exporter extends Shopalytic_Extractor_Model_ExporterBase {
	public function customers() {
		$customers = array();

		$customer_collection = Mage::getModel('customer/customer')->getCollection()
			->addAttributeToSelect('firstname')
			->addAttributeToSelect('lastname')
			->addAttributeToFilter('updated_at', array('from' => $this->last_update, 'to' => $this->stop_time));
		$customer_collection->getSelect()->limit($this->limit, $this->offset);

		if(!count($customer_collection)) {
			return array();
		}

		foreach($customer_collection as $customer) {
			$properties = array(
				'customer_id' => $customer->getId(),
				'first_name' => $customer->getFirstname(),
				'last_name' => $customer->getlastname(),
				'email' => $customer->getEmail(),
				'created_at' => $customer->getCreatedAt(),
				'updated_at' => $customer->getUpdatedAt()
			);

			$customers[] = $properties;
		}

		return $customers;
	}

	public function products() {
		$products = array();

		$product_collection = Mage::getModel('catalog/product')->getCollection()
			->addAttributeToSelect('name')
			->addAttributeToSelect('cost')
			->addAttributeToSelect('url_path')
			->addAttributeToSelect('price')
			->addAttributeToFilter('updated_at', array('from' => $this->last_update, 'to' => $this->stop_time));
		$product_collection->getSelect()->limit($this->limit, $this->offset);

		if(!count($product_collection)) {
			return array();
		}

		foreach($product_collection as $product) {
			$properties = array(
				'product_id' => $product->getId(),
				'sku' => $product->getSku(),
				'name' => $product->getName(),
				'price' => $product->getPrice(),
				'cost' => $product->getCost(),
				'url_path' => $product->getUrlPath(),
				'created_at' => $product->getCreatedAt(),
				'updated_at' => $product->getUpdatedAt()
			);

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

	public function orders() {
		$orders = array();

		$orders_collection = Mage::getModel('sales/order')->getCollection()
			->addAttributeToFilter('updated_at', array('from' => $this->last_update, 'to' => $this->stop_time));
		$orders_collection->getSelect()->limit($this->limit, $this->offset);

		if(!count($orders_collection)) {
			return array();
		}

		foreach($orders_collection as $order) {
			$shipping = $order->getShippingAddress();
			$billing = $order->getBillingAddress();

			$properties = array(
				'order_id' => $order->getIncrementId(),
				'cart_id' => $order->getQuoteId(),
				'status' => $order->getState(),
				'order_date' => $order->getCreatedAt(),
				'updated_at' => $order->getUpdatedAt(),
				'shipping_description' => $order->getShippingDescription(),
				'shipping_method' => $order->getShippingMethod(),
				'guest' => $order->getCustomerIsGuest(),
				'total_qty' => $order->getTotalQtyOrdered(),
				'customer_id' => $order->getCustomerId(),
				'customer_email' => $order->getCustomerEmail(),
				'customer_first_name' => $order->getCustomerFirstname(),
				'customer_last_name' => $order->getCustomerLastname(),
				'billing' => array(
					'city' => $billing->getCity(),
					'zipcode' => $billing->getPostcode(),
					'country' => Mage::getModel('directory/country')->load($billing->getCountryId())->getIso3Code()
				),
				'shipping' => array(
					'city' => $shipping->getCity(),
					'zipcode' => $shipping->getPostcode(),
					'country' => Mage::getModel('directory/country')->load($shipping->getCountryId())->getIso3Code()
				),
				'qty_ordered' => $order->getTotalQtyOrdered(),
				'total' => $order->getGrandTotal(),
				'subtotal' => $order->getSubtotal(),
				'total_shipping' => $order->getShippingAmount(),
				'total_tax' => $order->getTaxAmount(),
				'total_discount' => abs($order->getDiscountAmount()),
				'total_refund' => $order->getTotalRefunded(),
				'discount_refunded' => $order->getDiscountRefunded(),
				'shipping_refunded' => $order->getShippingRefunded(),
				'tax_refunded' => $order->getTaxRefunded()
			);

			// payment type
			$payment = $order->getPayment();
			$properties['payment_type'] = array(
				'type' => $payment->getMethod(),
				'cc_type' => $payment->getCcType()
			);

			// Discount
			if($order->getCouponCode()) {
				$properties['discounts'] = array(
					'code' => $order->getCouponCode(),
					'value' => abs($order->getDiscountAmount())
				);
			}

			// Line items
			$items = $order->getAllVisibleItems();
			if($items) {
				$properties['line_items'] = array();
				foreach ($items as $item) {
					$properties['line_items'][] = array(
						'sku' => $item['sku'],
						'product_id' => $item['product_id'],
						'qty_ordered' => $item['qty_ordered'],
						'qty_refunded' => $item['qty_refunded'],
						'qty_canceled' => $item['qty_canceled'],
						'price' => $item['price'],
						'amount_refunded' => $item['amount_refunded'],
						'cost' => $item['base_cost']
					);
				}
			}

			$tracking_numbers = Mage::getResourceModel('sales/order_shipment_track_collection')
	->setOrderFilter($order);
			if($tracking_numbers) {
				$properties['tracking_numbers'] = array();
				foreach($tracking_numbers as $track) {
					$properties['tracking_numbers'][] = array(
						'carrier' => $track->getCarrierCode(),
						'tracking_number' => $track->getTrackNumber(),
						'date_shipped' => $track->getCreatedAt()
					);
				}
			}

			$orders[] = $properties;
		}

		return $orders;
	}

	public function carts() {
		$quote_collection = Mage::getModel('sales/quote')->getCollection()
			->addFieldToFilter('updated_at', array('from' => $this->last_update, 'to' => $this->stop_time));
		$quote_collection->getSelect()->limit($this->limit, $this->offset);

		if(!count($quote_collection)) {
			return array();
		}

		foreach($quote_collection as $quote) {
			$totals = $quote->getTotals();

			$shipping = $quote->getShippingAddress();
			$billing = $quote->getBillingAddress();

			$properties = array(
				'cart_id' => $quote->getId(),
				'created_at' => $quote->getCreatedAt(),
				'updated_at' => $quote->getUpdatedAt(),
				'guest' => $quote->getCustomerIsGuest(),
				'customer_id' => $quote->getCustomerId(),
				'customer_email' => $quote->getCustomerEmail(),
				'customer_first_name' => $quote->getCustomerFirstname(),
				'customer_last_name' => $quote->getCustomerLastname(),
				'total' => $quote->getGrandTotal(),
				'subtotal' => $quote->getSubtotal()
			);

			$payment = $quote->getPayment();
			if($payment->getMethod() || $payment->getCcType()) {
				$properties['payment_type'] = array(
					'type' => $payment->getMethod(),
					'cc_type' => $payment->getCcType()
				);
			}

			if($shipping->city || $shipping->zipcode || $shipping->country) {
				$properties['total_shipping'] = $shipping->getShipping_amount();

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
				$properties['total_discount'] = abs($totals['discount']->getValue());
				$properties['discounts'] = array(
					'code' => $quote->getCouponCode(),
					'value' => abs($totals['discount']->getValue())
				);
			}

			if(isset($totals['tax'])) {
				$properties['total_tax'] = $totals['tax']->getValud();
			}

			// Line items
			$items = $quote->getAllVisibleItems();
			if($items) {
				$properties['line_items'] = array();
				foreach ($items as $item) {
					$properties['line_items'][] = array(
						'sku' => $item['sku'],
						'product_id' => $item['product_id'],
						'price' => $item['price'],
						'cost' => $item['base_cost'],
						'qty' => $item['qty']
					);
				}
			}

			$quotes[] = $properties;
		}

		return $quotes;
	}
}
