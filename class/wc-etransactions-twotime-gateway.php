<?php

class WC_Etransactions_Twotime_Gateway extends WC_Etransactions_Abstract_Gateway {
	protected $defaultTitle = 'E-Transactions 2 times payment';
	protected $defaultDesc = 'xxxx';
	protected $type = 'twotime';

	public function __construct() {
		// Some properties
		$this->id = 'etransactions_2x';
		$this->method_title = __('E-Transactions 2 times', WC_ETRANSACTIONS_PLUGIN);
		$this->has_fields = false;
		//$this->icon = TODO;

		parent::__construct();
	}

	private function _showDetailRow($label, $value) {
		return '<strong>'.$label.'</strong> '.$value;
	}

	/**
	 * Check If The Gateway Is Available For Use
	 *
	 * @access public
	 * @return bool
	 */
	public function is_available() {
		if (!parent::is_available()) {
			return false;
		}
		$minimal = $this->_config->getAmount();
		if (empty($minimal)) {
			return true;
		}
		$total = WC()->cart->total;
		$minimal = floatval($minimal);
		return $total >= $minimal;
	}

	public function showDetails($order) {
		$orderId = $order->get_id();
		$payment = $this->_etransactions->getOrderPayments($orderId, 'first_payment');
		if (!empty($payment)) {
			$data = unserialize($payment->data);
			$payment = $this->_etransactions->getOrderPayments($orderId, 'second_payment');
			if (!empty($payment)) {
				$second = unserialize($payment->data);
			}

			$rows = array();
			$rows[] = $this->_showDetailRow(__('Reference:', WC_ETRANSACTIONS_PLUGIN), $data['reference']);
			if (isset($data['ip'])) {
				$rows[] = $this->_showDetailRow(__('Country of IP:', WC_ETRANSACTIONS_PLUGIN), $data['ip']);
			}
			$rows[] = $this->_showDetailRow(__('Processing date:', WC_ETRANSACTIONS_PLUGIN), preg_replace('/^([0-9]{2})([0-9]{2})([0-9]{4})$/', '$1/$2/$3', $data['date'])." - ".$data['time']);
			if (isset($data['firstNumbers']) && isset($data['lastNumbers'])) {
				$rows[] = $this->_showDetailRow(__('Card numbers:', WC_ETRANSACTIONS_PLUGIN), $data['firstNumbers'].'...'.$data['lastNumbers']);
			}
			if (isset($data['validity'])) {
				$rows[] = $this->_showDetailRow(__('Validity date:', WC_ETRANSACTIONS_PLUGIN), preg_replace('/^([0-9]{2})([0-9]{2})$/', '$2/$1', $data['validity']));
			}

	        $date = preg_replace('/^([0-9]{2})([0-9]{2})([0-9]{4})$/', '$1/$2/$3', $data['date']);
	        $value = sprintf('%s (%s)', $data['amount'] / 100.0, $date);
			$rows[] = $this->_showDetailRow(__('First debit:'), $value);

			if (isset($second)) {
	            $date = preg_replace('/^([0-9]{2})([0-9]{2})([0-9]{4})$/', '$1/$2/$3', $second['date']);
	            $value = sprintf('%s (%s)', $second['amount'] / 100.0, $date);
			}
			else {
				$value = __('Not achieved', WC_ETRANSACTIONS_PLUGIN);
			}
			$rows[] = $this->_showDetailRow(__('Second debit:'), $value);


			$rows[] = $this->_showDetailRow(__('Transaction:', WC_ETRANSACTIONS_PLUGIN), $data['transaction']);
			$rows[] = $this->_showDetailRow(__('Call:', WC_ETRANSACTIONS_PLUGIN), $data['call']);
			$rows[] = $this->_showDetailRow(__('Authorization:', WC_ETRANSACTIONS_PLUGIN), $data['authorization']);

			echo '<h4>'.__('Payment information', WC_ETRANSACTIONS_PLUGIN).'</h4>';
			echo '<p>'.implode('<br/>', $rows).'</p>';
		}
	}
}
