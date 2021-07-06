<?php

/**
 * E-Transactions - Individual Payment Gateway class.
 *
 * @class   WC_EStdGw
 * @extends WC_Etransactions_Abstract_Gateway
 */
class WC_EStdGw extends WC_Etransactions_Abstract_Gateway
{
    protected $defaultTitle = 'Secured payment by Credit Agricole';
    protected $defaultDesc = 'Choose your mean of payment directly on secured payment page of Credit Agricole';
    protected $type = 'standard';

    public function __construct()
    {
        // Some properties
        $this->id = 'etransactions_std';
        $this->method_title = __('Credit Agricole', WC_ETRANSACTIONS_PLUGIN);
        $this->originalTitle = $this->title = __('Secured payment by Credit Agricole', WC_ETRANSACTIONS_PLUGIN);
        $this->defaultDesc = __('Choose your mean of payment directly on secured payment page of Credit Agricole', WC_ETRANSACTIONS_PLUGIN);
        $this->has_fields = false;
        $this->icon = 'CB.svg';
        // $this->icon = apply_filters('woocommerce_paypal_icon', WC()->plugin_url() . '/assets/images/icons/paypal.png');

        parent::__construct();
    }

    private function _showDetailRow($label, $value)
    {
        return '<strong>'.$label.'</strong> '.__($value, WC_ETRANSACTIONS_PLUGIN);
    }

    /**
     * Display card type, logo & amount
     *
     * @param array $data
     * @return string
     */
    private function showCardType($data)
    {
        $cardType = null;
        if (isset($data['cardType'])) {
            $originalCardType = $cardType = strtoupper($data['cardType']);
            if (in_array($cardType, array('LIMOCB', 'VISA', 'MASTERCARD', 'EUROCARD_MASTERCARD', 'CB'))) {
                $cardType = 'CB';
            }
        }

        return $this->_showDetailRow(__('Card type:', WC_ETRANSACTIONS_PLUGIN), '<img title="'. $originalCardType .'" alt="'. $originalCardType .'" src="' . apply_filters(WC_ETRANSACTIONS_PLUGIN, plugin_dir_url(__DIR__) . 'cards/') . $cardType . '.svg" />') .
        ' - ' . $this->_showDetailRow(__('Amount:', WC_ETRANSACTIONS_PLUGIN), wc_price($data['amount']/100));
    }

    public function showDetails($order)
    {
        $orderId = $order->get_id();
        // Capture
        $payment = $this->_etransactions->getOrderPayments($orderId, 'capture');
        // Authorization
        $authorizationPayment = $this->_etransactions->getOrderPayments($orderId, 'authorization');
        // LIMONETIK case
        $limonetikFirstPaymentData = $limonetikSecondPaymentData = null;
        $limonetikFirstPayment = $this->_etransactions->getOrderPayments($orderId, 'first_payment');
        if (!empty($limonetikFirstPayment)) {
            $limonetikFirstPaymentData = unserialize($limonetikFirstPayment->data);
        }
        $limonetikSecondPayment = $this->_etransactions->getOrderPayments($orderId, 'second_payment');
        if (!empty($limonetikSecondPayment)) {
            $limonetikSecondPaymentData = unserialize($limonetikSecondPayment->data);
        }

        // Set the main payment using the first limonetik transaction
        if (!empty($limonetikFirstPayment)) {
            $payment = $limonetikFirstPayment;
        }

        if (empty($payment)) {
            if (empty($authorizationPayment)) {
                return;
            } else {
                $payment = $authorizationPayment;
                unset($authorizationPayment);
            }
        }

        // Unserialize using the data from authorization or capture info
        $data = unserialize($payment->data);
        if (isset($data['CODEREPONSE']) && !empty($authorizationPayment)) {
            $data = unserialize($authorizationPayment->data);
        }

        $rows = array();
        $rows[] = $this->_showDetailRow(__('Reference:', WC_ETRANSACTIONS_PLUGIN), $data['reference']);
        if (!empty($data['ip'])) {
            $rows[] = $this->_showDetailRow(__('Country of IP:', WC_ETRANSACTIONS_PLUGIN), $data['ip']);
        }
        $rows[] = $this->_showDetailRow(__('Processing date:', WC_ETRANSACTIONS_PLUGIN), preg_replace('/^([0-9]{2})([0-9]{2})([0-9]{4})$/', '$1/$2/$3', $data['date'])." - ".$data['time']);
        if (!empty($data['cardType'])) {
            $rows[] = $this->showCardType($data);
        }
        if (!empty($limonetikSecondPaymentData['cardType'])) {
            $rows[] = $this->showCardType($limonetikSecondPaymentData);
        }
        if (!empty($data['firstNumbers']) && !empty($data['lastNumbers'])) {
            $rows[] = $this->_showDetailRow(__('Card numbers:', WC_ETRANSACTIONS_PLUGIN), $data['firstNumbers'].'...'.$data['lastNumbers']);
        }
        if (!empty($data['validity'])) {
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

        $rows[] = $this->_showDetailRow(__('Transaction:', WC_ETRANSACTIONS_PLUGIN), $data['transaction']);
        $rows[] = $this->_showDetailRow(__('Call:', WC_ETRANSACTIONS_PLUGIN), $data['call']);
        if (!empty($data['authorization'])) {
            $rows[] = $this->_showDetailRow(__('Authorization:', WC_ETRANSACTIONS_PLUGIN), $data['authorization']);
        }

        echo '<h4>'.__('Payment information', WC_ETRANSACTIONS_PLUGIN).'</h4>';
        echo '<p>'.implode('<br/>', $rows).'</p>';

        // Display capture infos
        if (!empty($payment) && !empty($authorizationPayment)) {
            echo '<h4>'.__('Capture information', WC_ETRANSACTIONS_PLUGIN).'</h4>';
            $capturePaymentData = unserialize($payment->data);
            $rowsCapture = array();
            $rowsCapture[] = $this->_showDetailRow(__('Transaction:', WC_ETRANSACTIONS_PLUGIN), $capturePaymentData['NUMTRANS']);
            $rowsCapture[] = $this->_showDetailRow(__('Call:', WC_ETRANSACTIONS_PLUGIN), $capturePaymentData['NUMAPPEL']);
            $rowsCapture[] = $this->_showDetailRow(__('Authorization:', WC_ETRANSACTIONS_PLUGIN), $capturePaymentData['AUTORISATION']);
            $rowsCapture[] = $this->_showDetailRow(__('Processing date:', WC_ETRANSACTIONS_PLUGIN), wp_date(get_option('date_format') . ' - ' . get_option('time_format'), $capturePaymentData['CAPTURE_DATE_ADD']));
            echo '<p>'.implode('<br/>', $rowsCapture).'</p>';
        }
    }
}
