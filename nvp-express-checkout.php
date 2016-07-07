<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('PPL_MODE', 'sandbox');

define('PPL_API_USER', '');
define('PPL_API_PASSWORD', '');
define('PPL_API_SIGNATURE', '');

define('PPL_LANG', 'EN');
define('PPL_LOGO_IMG', '');
define('PPL_CURRENCY_CODE', 'JPY');

define('PPL_RETURN_URL', 'https://www.sumonselim.com/?payment=true');
define('PPL_CANCEL_URL', 'https://www.sumonselim.com/?payment=false');

/**
 * Paypal Express Checkout Payment
 *
 */
class MyPayPal
{
    /**
     * Process payment request for single product.
     *
     * @param $item
     *
     * @return array
     */
    public function processPaymentRequest(array $item)
    {
        $padata = '&METHOD=SetExpressCheckout';

        $padata .= '&RETURNURL=' . urlencode(PPL_RETURN_URL);
        $padata .= '&CANCELURL=' . urlencode(PPL_CANCEL_URL);
        $padata .= '&PAYMENTREQUEST_0_PAYMENTACTION=' . urlencode('SALE');

        $padata .= '&L_PAYMENTREQUEST_0_NAME0=' . urlencode($item['title']);
        $padata .= '&L_PAYMENTREQUEST_0_NUMBER0=' . urlencode($item['bookingID']);
        $padata .= '&L_PAYMENTREQUEST_0_DESC0=' . urlencode($item['description']);
        $padata .= '&L_PAYMENTREQUEST_0_AMT0=' . urlencode($item['amount']);
        $padata .= '&L_PAYMENTREQUEST_0_QTY0=' . urlencode($item['quantity']);

        $padata .= '&NOSHIPPING=1';
        $padata .= '&PAYMENTREQUEST_0_ITEMAMT=' . urlencode($item['amount']);

        $padata .= '&PAYMENTREQUEST_0_TAXAMT=0';
        $padata .= '&PAYMENTREQUEST_0_SHIPPINGAMT=0';
        $padata .= '&PAYMENTREQUEST_0_HANDLINGAMT=0';
        $padata .= '&PAYMENTREQUEST_0_SHIPDISCAMT=0';
        $padata .= '&PAYMENTREQUEST_0_INSURANCEAMT=0';
        $padata .= '&PAYMENTREQUEST_0_AMT=' . urlencode($item['amount']);
        $padata .= '&PAYMENTREQUEST_0_CURRENCYCODE=' . urlencode(PPL_CURRENCY_CODE);

        $padata .= '&LOCALECODE=' . PPL_LANG; // PayPal pages to match the language on your website;
        $padata .= '&LOGOIMG=' . PPL_LOGO_IMG; // Site logo

        $_SESSION['ppl_item'] = $item;

        $httpParsedResponseAr = $this->PPHttpPost('SetExpressCheckout', $padata);

        // Respond according to message we receive from Paypal
        if ('SUCCESS' == strtoupper($httpParsedResponseAr['ACK']) || 'SUCCESSWITHWARNING' == strtoupper($httpParsedResponseAr['ACK'])) {
            $PAYPAL_URL = 'https://www.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=';
            if (PPL_MODE === 'sandbox') {
                $PAYPAL_URL = 'https://www.sandbox.paypal.com/webscr?cmd=_express-checkout&token=';
            }

            $PAYPAL_URL .= $httpParsedResponseAr['TOKEN'] . '';
            header('Location: ' . $PAYPAL_URL);
        } else {
            $data = array();
            $data['message'] = 'error';
            $data['body'] = $httpParsedResponseAr;

            return $data;
        }
    }


    /**
     * Process payment.
     *
     * @return array
     */
    public function processPayment()
    {
        $data = array();
        if (!empty($_SESSION['ppl_item'])) {
            $item = $_SESSION['ppl_item'];

            $padata = '&TOKEN=' . urlencode($_GET['token']);
            $padata .= '&PAYERID=' . urlencode($_GET['PayerID']);
            $padata .= '&PAYMENTREQUEST_0_PAYMENTACTION=' . urlencode('SALE');

            $padata .= '&L_PAYMENTREQUEST_0_NAME0=' . urlencode($item['title']);
            $padata .= '&L_PAYMENTREQUEST_0_NUMBER0=' . urlencode($item['bookingID']);
            $padata .= '&L_PAYMENTREQUEST_0_DESC0=' . urlencode($item['description']);
            $padata .= '&L_PAYMENTREQUEST_0_AMT0=' . urlencode($item['amount']);
            $padata .= '&L_PAYMENTREQUEST_0_QTY0=' . urlencode($item['quantity']);

            $padata .= '&PAYMENTREQUEST_0_ITEMAMT=' . urlencode($item['amount']);
            $padata .= '&PAYMENTREQUEST_0_TAXAMT=0';
            $padata .= '&PAYMENTREQUEST_0_SHIPPINGAMT=0';
            $padata .= '&PAYMENTREQUEST_0_HANDLINGAMT=0';
            $padata .= '&PAYMENTREQUEST_0_SHIPDISCAMT=0';
            $padata .= '&PAYMENTREQUEST_0_INSURANCEAMT=0';
            $padata .= '&PAYMENTREQUEST_0_AMT=' . urlencode($item['amount']);
            $padata .= '&PAYMENTREQUEST_0_CURRENCYCODE=' . urlencode(PPL_CURRENCY_CODE);

            // process the payment
            $httpParsedResponseAr = $this->PPHttpPost('DoExpressCheckoutPayment', $padata);

            // if payment was successful
            if ('SUCCESS' == strtoupper($httpParsedResponseAr['ACK']) || 'SUCCESSWITHWARNING' == strtoupper($httpParsedResponseAr['ACK'])) {
                unset($_SESSION['ppl_item']);
                $data['transaction_id'] = urldecode($httpParsedResponseAr['PAYMENTINFO_0_TRANSACTIONID']);
                $data['payment_status'] = urldecode($httpParsedResponseAr['PAYMENTINFO_0_PAYMENTSTATUS']);

                return $this->GetTransactionDetails($data);
            } else {
                $data['message'] = 'error';
                $data['body'] = $httpParsedResponseAr;

                return $data;
            }
        } else {
            return $this->GetTransactionDetails($data);
        }
    }

    /**
     * Get transaction details data.
     *
     * @param array $data
     *
     * @return array
     */
    private function GetTransactionDetails(array $data)
    {
        $padata = '&TOKEN=' . urlencode($_GET['token']);
        $httpParsedResponseAr = $this->PPHttpPost('GetExpressCheckoutDetails', $padata, PPL_API_USER, PPL_API_PASSWORD, PPL_API_SIGNATURE, PPL_MODE);

        if ('SUCCESS' == strtoupper($httpParsedResponseAr['ACK']) || 'SUCCESSWITHWARNING' == strtoupper($httpParsedResponseAr['ACK'])) {
            $data['message'] = 'success';
            $data['body'] = $httpParsedResponseAr;
        } else {
            $data['message'] = 'error';
            $data['body'] = $httpParsedResponseAr;
        }

        return $data;
    }

    /**
     * Send HTTP request to PayPal Server.
     *
     * @param $methodName
     * @param $nvpString
     *
     * @return array
     */
    private function PPHttpPost($methodName, $nvpString)
    {
        // Set up your API credentials, PayPal end point, and API version.
        $API_UserName = urlencode(PPL_API_USER);
        $API_Password = urlencode(PPL_API_PASSWORD);
        $API_Signature = urlencode(PPL_API_SIGNATURE);

        $API_Endpoint = 'https://api-3t.paypal.com/nvp';
        if (PPL_MODE === 'sandbox') {
            $API_Endpoint = 'https://api-3t.sandbox.paypal.com/nvp';
        }

        $version = urlencode('109.0');

        // Set the curl parameters.
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $API_Endpoint);
        curl_setopt($ch, CURLOPT_VERBOSE, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);

        // Set the API operation, version, and API signature in the request.
        $nvpreq = "METHOD=$methodName&VERSION=$version&PWD=$API_Password&USER=$API_UserName&SIGNATURE=$API_Signature$nvpString";

        // Set the request as a POST FIELD for curl.
        curl_setopt($ch, CURLOPT_POSTFIELDS, $nvpreq);

        // Get response from the server.
        $httpResponse = curl_exec($ch);

        if (!$httpResponse) {
            exit("$methodName failed: " . curl_error($ch) . '(' . curl_errno($ch) . ')');
        }

        // Extract the response details.
        $httpResponseAr = explode('&', $httpResponse);

        $httpParsedResponseAr = array();
        foreach ($httpResponseAr as $i => $value) {
            $tmpAr = explode('=', $value);

            if (count($tmpAr) > 1) {
                $httpParsedResponseAr[$tmpAr[0]] = $tmpAr[1];
            }
        }

        if (!array_key_exists('ACK', $httpParsedResponseAr)) {
            exit("Invalid HTTP Response for POST request($nvpreq) to $API_Endpoint.");
        }

        return $httpParsedResponseAr;
    }
}
