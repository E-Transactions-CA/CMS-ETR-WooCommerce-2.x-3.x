<?php

/**
 * E-Transactions - cURL helper class
 *
 * @class   WC_Etransactions_Curl_Helper
 */
class WC_Etransactions_Curl_Helper
{
    protected $config;
    protected $etransactions;

    const ACCESS_API_VERSION = '00103';
    const PREMIUM_API_VERSION = '00104';
    const CAPTURE_OPERATION = '00002';

    public function __construct(WC_Etransactions_Config $config)
    {
        $this->config = $config;
        $this->etransactions = new WC_Etransactions($this->config);
    }

    /**
     * Init the cURL handler
     *
     * @return resource
     */
    protected function initCurl()
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_CAINFO, dirname(__FILE__).'/../assets/cacert.pem');
        curl_setopt($ch, CURLOPT_USERAGENT, sprintf('%s - %s', WC_ETRANSACTIONS_PLUGIN, WC_ETRANSACTIONS_VERSION));

        return $ch;
    }

    /**
     * Test a specific URL via cURL
     *
     * @param string $url
     * @return string
     */
    protected function curlCheckUrl($url)
    {
        $ch = $this->initCurl();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            throw new Exception(sprintf('Curl error (code "%d") - %s', curl_errno($ch), curl_error($ch)));
        }
        curl_close($ch);

        return $httpCode;
    }

    /**
     * Make a call to the API using cURL
     *
     * @param string $url
     * @param array $params
     * @return mixed
     */
    protected function curl($url, $params)
    {
        $ch = $this->initCurl();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));

        $result = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new Exception(sprintf('Curl error (code "%d") - %s', curl_errno($ch), curl_error($ch)));
        }
        curl_close($ch);

        return $result;
    }

    /**
     * Build parameters for the current action
     *
     * @param WC_Order $order
     * @param string $typeOfOperation
     * @param string $transactionId
     * @param string $callId
     * @param string $amount
     * @return array
     */
    protected function buildParameters(WC_Order $order, $typeOfOperation, $transactionId, $callId, $amount)
    {
        $now = new DateTime('now', new DateTimeZone('Europe/Paris'));

        $apiVersion = self::ACCESS_API_VERSION;
        if ($this->config->isPremium()) {
            $apiVersion = self::PREMIUM_API_VERSION;
        }

        $fields = array(
            'ACTIVITE' => '024',
            'VERSION' => $apiVersion,
            'CLE' => $this->config->getPassword(),
            'DATEQ' => $now->format('dmYHis'),
            'DEVISE' => sprintf('%03d', WC_Etransactions_Iso4217Currency::getIsoCode($order->get_currency())),
            'IDENTIFIANT' => $this->config->getIdentifier(),
            'MONTANT' => sprintf('%010d', $amount),
            'NUMAPPEL' => sprintf('%010d', $callId),
            'NUMQUESTION' => sprintf('%010d', $now->format('U')),
            'NUMTRANS' => sprintf('%010d', $transactionId),
            'RANG' => sprintf('%03d', $this->config->getRank()),
            'REFERENCE' => $order->get_id() . ' - ' . $this->etransactions->getBillingName($order),
            'SITE' => sprintf('%07d', $this->config->getSite()),
            'TYPE' => $typeOfOperation,
        );

        return $fields;
    }

    /**
     * Make the API call
     *
     * @param WC_Order $order
     * @param string $typeOfOperation
     * @param string $transactionId
     * @param string $callId
     * @param string $amount
     * @param string $cardType
     * @return mixed
     */
    protected function makeApiCall(WC_Order $order, $typeOfOperation, $transactionId, $callId, $amount, $cardType)
    {
        $fields = $this->buildParameters($order, $typeOfOperation, $transactionId, $callId, $amount);
        // Sign values
        $sign = $this->etransactions->signValues($fields);
        // Hash HMAC
        $fields['HMAC'] = $sign;

        // Add ACQUEREUR for some cards
        switch ($cardType) {
            case 'PAYPAL':
                $fields['ACQUEREUR'] = 'PAYPAL';
                break;
        }

        $urls = $this->config->getDirectUrls();
        $url = $this->getFirstAvailableUrl($urls);
        if ($url === null) {
            throw new Exception('E-Transactions is not available. Please try again later.');
        }
        $result = $this->curl($url, $fields);

        $data = array();
        parse_str($result, $data);

        return $data;
    }

    /**
     * Try to reach the URL, return the first working one
     *
     * @param array $urls
     * @return string|null
     */
    protected function getFirstAvailableUrl($urls)
    {
        foreach ($urls as $url) {
            $urlToCheck = preg_replace('#^([a-zA-Z0-9]+://[^/]+)(/.*)?$#', '\1/load.html', $url);
            if ($this->curlCheckUrl($urlToCheck) == 200) {
                return $url;
            }
        }

        return null;
    }

    /**
     * Ask for a capture, use Direct method
     *
     * @param WC_Order $order
     * @param string $transactionId
     * @param string $callId
     * @param string $amount
     * @param string $cardType
     * @return array
     */
    public function makeCapture(WC_Order $order, $transactionId, $callId, $amount, $cardType = null)
    {
        // 00002 => Capture
        return $this->makeApiCall($order, self::CAPTURE_OPERATION, $transactionId, $callId, $amount, $cardType);
    }
}
