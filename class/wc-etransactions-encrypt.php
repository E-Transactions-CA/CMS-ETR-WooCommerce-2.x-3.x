<?php
/**
 * E-Transactions WooCommerce Module
 *
 * Feel free to contact E-Transactions at support@e-transactions.fr for any
 * question.
 *
 * LICENSE: This source file is subject to the version 3.0 of the Open
 * Software License (OSL-3.0) that is available through the world-wide-web
 * at the following URI: http://opensource.org/licenses/OSL-3.0. If
 * you did not receive a copy of the OSL-3.0 license and are unable
 * to obtain it through the web, please send a note to
 * support@e-transactions.fr so we can mail you a copy immediately.
 *
 * @author Guillaume - BM Services (http://www.bm-services.com)
 * @copyright 2012-2015 E-Transactions
 * @license http://opensource.org/licenses/OSL-3.0
 * @link http://www.e-transactions.fr/
 * @since 2
 * */

class ETransactionsEncrypt
{
    /*IV generation */
    private function _MakeIv($key)
    {
        if (function_exists('openssl_cipher_iv_length')) {
            //openssl
            $len = openssl_cipher_iv_length('AES-128-CBC');
            $strong_crypto = true;
            $iv = openssl_random_pseudo_bytes($len, $strong_crypto);
        } else {
            //mcrypt
            $td = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', MCRYPT_MODE_CBC, '');
            $size = mcrypt_enc_get_iv_size($td);
            $iv = mcrypt_create_iv($size, MCRYPT_RAND);
        }
        return bin2hex($iv);
    }

    /*
     * You can change this method
     if you want to change the way the key is generated.
     */
    public function generateKey()
    {
        // generate key, write to KEY_FILE_PATH
        $key = openssl_random_pseudo_bytes(16);
        if (file_exists(WC_ETRANSACTIONS_KEY_PATH)) {
            unlink(WC_ETRANSACTIONS_KEY_PATH);
        }
        $key = bin2hex($key);
        $iv = $this->_MakeIv($key);
        return file_put_contents(WC_ETRANSACTIONS_KEY_PATH, "<?php" . $key . $iv);
    }
    /**
     * @return string Key used for encryption
     */
    private function _getKey()
    {
        //check whether key on KEY_FILE_PATH exists, if not generate it.
        $ok = true;
        if (!file_exists(WC_ETRANSACTIONS_KEY_PATH)) {
            $ok = $this->generateKey();
            $_POST['KEY_ERROR'] = __("For some reason, the key has just been generated. please reenter the HMAC key to crypt it.");
        }
        if ($ok!==false) {
            $key = file_get_contents(WC_ETRANSACTIONS_KEY_PATH);
            $key = substr($key, 5, 32);
            return $key;
        }
    }
    /**
     * @return string Key used for encryption
     */
    private function _getIv()
    {
        //check whether key on KEY_FILE_PATH exists, if not generate it.
        if (!file_exists(WC_ETRANSACTIONS_KEY_PATH)) {
            $this->generateKey();
            $_POST['KEY_ERROR'] = __("For some reason, the key has just been generated. please reenter the HMAC key to crypt it.");
        } else {
            $iv = file_get_contents(WC_ETRANSACTIONS_KEY_PATH);
            $iv = substr($iv, 37, 16);
            return $iv;
        }
    }

    private function _crypt($key, $iv, $data)
    {
        if (function_exists('openssl_encrypt')) {
            //openssl
            $result = openssl_encrypt($data, 'aes-128-cbc', $key, OPENSSL_RAW_DATA, $iv);
        } else {
            // Prepare mcrypt
            $td = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', MCRYPT_MODE_CBC, '');
            mcrypt_generic_init($td, $key, $iv);
            // Encrypt
            $result =  mcrypt_generic($td, $data);
        }
        // Encode (to avoid data loose when saved to database or
        // any storage that does not support null chars)
        return base64_encode($result);
    }

    /**
     * Encrypt $data using AES
     * @param string $data The data to encrypt
     * @return string The result of encryption
     */
    public function encrypt($data)
    {
        if (empty($data)) {
            return '';
        }
        // First encode data to base64 (see end of descrypt)
        $data = base64_encode($data);

        // Prepare key
        $key = $this->_getKey();
        $key = substr($key, 0, 24);
        while (strlen($key) < 24) {
            $key .= substr($key, 0, 24 - strlen($key));
        }
        // Init vector
        $iv = $this->_getIv();

        return $this->_crypt($key, $iv, $data);
    }

    private function _decrypt($key, $iv, $data)
    {
        if (function_exists('openssl_decrypt')) {
            //openssl
            $result = openssl_decrypt($data, 'aes-128-cbc', $key, OPENSSL_RAW_DATA, $iv);
            if (!$result && function_exists('mcrypt_module_open')) {
                $td = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', MCRYPT_MODE_CBC, '');
                mcrypt_generic_init($td, $key, $iv);
                // Decrypt
                $result = mdecrypt_generic($td, $data);
            }
            if (!$result) {
                show_message(new WP_Error("", "", "ATTENTION:le module ne peut plus afficher la clef HMAC, veuillez l'initiailiser de nouveau."));
            }
        } else {
            // Prepare mcrypt
            $td = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', MCRYPT_MODE_CBC, '');
            mcrypt_generic_init($td, $key, $iv);
            // Decrypt
            $result = mdecrypt_generic($td, $data);
        }
        // Decode data
        return base64_decode($result);
    }

    /**
     * Decrypt $data using 3DES
     * @param string $data The data to decrypt
     * @return string The result of decryption
     * @see PAYBOX_Epayment_Helper_Encrypt::_getKey()
     */
    public function decrypt($data)
    {
        if (empty($data)) {
            return '';
        }

        // First decode encrypted message (see end of encrypt)
        $data = base64_decode($data);

        // Prepare key
        $key = $this->_getKey();
        $key = substr($key, 0, 24);
        while (strlen($key) < 24) {
            $key .= substr($key, 0, 24 - strlen($key));
        }

        // Init vector
        $iv = $this->_getIv();
        $result = $this->_decrypt($key, $iv, $data);
        // Remove any null char (data is base64 encoded so no data loose)
        $result = rtrim($result, "\0");

        return $result;
    }
}
