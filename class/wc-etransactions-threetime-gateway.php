<?php

/**
 * E-Transactions 3 times - Payment Gateway class.
 *
 * Extended by individual payment gateways to handle payments.
 *
 * @class   WC_E3Gw
 * @extends WC_Etransactions_Abstract_Gateway
 */
class WC_E3Gw extends WC_Etransactions_Abstract_Gateway
{
    protected $defaultTitle = 'Secured 3 times payment by Credit Agricole';
    protected $defaultDesc = 'Choose your mean of payment directly on secured payment page of Credit Agricole';
    protected $type = 'threetime';

    public function __construct()
    {
        // Some properties
        $this->id = 'etransactions_3x';
        $this->method_title = __('Credit Agricole 3 times', WC_ETRANSACTIONS_PLUGIN);
        $this->originalTitle = $this->title = __('Secured 3 times payment by Credit Agricole', WC_ETRANSACTIONS_PLUGIN);
        $this->defaultDesc = __('Choose your mean of payment directly on secured payment page of Credit Agricole', WC_ETRANSACTIONS_PLUGIN);
        $this->has_fields = false;
        $this->icon = 'CB.svg';
        //$this->icon = 'TODO';

        parent::__construct();
    }

    private function _showDetailRow($label, $value)
    {
        return '<strong>'.$label.'</strong> '.$value;
    }

    /**
     * Check If The Gateway Is Available For Use
     *
     * @access public
     * @return bool
     */
    public function is_available()
    {
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

    public function showDetails($order)
    {
        $orderId = $order->get_id();
        $payment = $this->_etransactions->getOrderPayments($orderId, 'first_payment');

        if (empty($payment)) {
            return;
        }

        $data = unserialize($payment->data);
        $payment = $this->_etransactions->getOrderPayments($orderId, 'second_payment');
        if (!empty($payment)) {
            $second = unserialize($payment->data);
        }
        $payment = $this->_etransactions->getOrderPayments($orderId, 'third_payment');
        if (!empty($payment)) {
            $third = unserialize($payment->data);
        }

        $rows = array();
        $rows[] = $this->_showDetailRow(__('Reference:', WC_ETRANSACTIONS_PLUGIN), $data['reference']);
        if (isset($data['ip'])) {
            $rows[] = $this->_showDetailRow(__('Country of IP:', WC_ETRANSACTIONS_PLUGIN), $data['ip']);
        }
        $rows[] = $this->_showDetailRow(__('Processing date:', WC_ETRANSACTIONS_PLUGIN), preg_replace('/^([0-9]{2})([0-9]{2})([0-9]{4})$/', '$1/$2/$3', $data['date'])." - ".$data['time']);
        if (isset($data['cardType'])) {
            $originalCardType = $cardType = strtoupper($data['cardType']);
            if (in_array($cardType, array('VISA', 'MASTERCARD', 'EUROCARD_MASTERCARD', 'CB'))) {
                $cardType = 'CB';
            }
            $rows[] = $this->_showDetailRow(__('Card type:', WC_ETRANSACTIONS_PLUGIN), '<img title="'. $originalCardType .'" alt="'. $originalCardType .'" src="' . apply_filters(WC_ETRANSACTIONS_PLUGIN, plugin_dir_url(__DIR__) . 'cards/') . $cardType . '.svg" />');
        }
        if (isset($data['firstNumbers']) && isset($data['lastNumbers'])) {
            $rows[] = $this->_showDetailRow(__('Card numbers:', WC_ETRANSACTIONS_PLUGIN), $data['firstNumbers'].'...'.$data['lastNumbers']);
        }
        if (isset($data['validity'])) {
            $rows[] = $this->_showDetailRow(__('Validity date:', WC_ETRANSACTIONS_PLUGIN), preg_replace('/^([0-9]{2})([0-9]{2})$/', '$2/$1', $data['validity']));
        }

        // 3DS Version
        if (!empty($data['3ds']) && $data['3ds'] == 'Y') {
            $cc_3dsVersion = '1.0.0';
            if (!empty($data['3dsVersion'])) {
                $cc_3dsVersion = str_replace('3DSv', '', trim($data['3dsVersion']));
            }
            $rows[] = $this->_showDetailRow(__('3DS version:', WC_ETRANSACTIONS_PLUGIN), $cc_3dsVersion);
        }

        $date = preg_replace('/^([0-9]{2})([0-9]{2})([0-9]{4})$/', '$1/$2/$3', $data['date']);
        $value = sprintf('%s (%s)', $data['amount'] / 100.0, $date);
        $rows[] = $this->_showDetailRow(__('First debit:'), $value);

        if (isset($second)) {
            $date = preg_replace('/^([0-9]{2})([0-9]{2})([0-9]{4})$/', '$1/$2/$3', $second['date']);
            $value = sprintf('%s (%s)', $second['amount'] / 100.0, $date);
        } else {
            $value = __('Not achieved', WC_ETRANSACTIONS_PLUGIN);
        }
        $rows[] = $this->_showDetailRow(__('Second debit:'), $value);

        if (isset($third)) {
            $date = preg_replace('/^([0-9]{2})([0-9]{2})([0-9]{4})$/', '$1/$2/$3', $third['date']);
            $value = sprintf('%s (%s)', $third['amount'] / 100.0, $date);
        } else {
            $value = __('Not achieved', WC_ETRANSACTIONS_PLUGIN);
        }
        $rows[] = $this->_showDetailRow(__('Third debit:'), $value);

        $rows[] = $this->_showDetailRow(__('Transaction:', WC_ETRANSACTIONS_PLUGIN), $data['transaction']);
        $rows[] = $this->_showDetailRow(__('Call:', WC_ETRANSACTIONS_PLUGIN), $data['call']);
        $rows[] = $this->_showDetailRow(__('Authorization:', WC_ETRANSACTIONS_PLUGIN), $data['authorization']);

        echo '<h4>'.__('Payment information', WC_ETRANSACTIONS_PLUGIN).'</h4>';
        echo '<p>'.implode('<br/>', $rows).'</p>';
    }
}

/**
 * E-Transactions 3 times - Payment Gateway class.
 *
 * Extended by individual payment gateways to handle payments.
 *
 * @class   WC_Etransactions_Threetime_GateWay
 * @extends WC_E3Gw
 */
class WC_Etransactions_Threetime_GateWay extends WC_E3Gw
{
    public function is_available()
    {
        return false;
    }
    public function receipt_page($orderId)
    {
        return;
    }
}
