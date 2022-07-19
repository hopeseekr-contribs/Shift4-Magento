<?php

namespace Shift4\Payment\Model;

use Magento\Framework\Serialize\Serializer\Serialize;
use Magento\Store\Model\ScopeInterface;
use Shift4\Payment\Model\Shift4;
use Magento\Framework\App\Config\ScopeConfigInterface as ScopeConfig;

class Api
{
    private $accessToken = '491AB6AD-2EF3-4749-B292-5B2D899CB1CB';
    private $endpoint = 'https://utgapi.shift4test.com/api/rest/v1/';
    private $clerk = '2009';
    private $interfaceName = 'S4PM_Magento_1.1.18';
    private $companyName = 'Shift4 corporation';
    private $interfaceVersion = '1.1.18';
    private $checkoutSession;
    private $scopeConfig;
    private $pageFactory;
    private $verifySSL;
    private $session;
    private $logger;
    private $authSession;
    private $clientGuid = 'A3B18F21-AD17-8416-0626C4C9F1CA86A7';
    private $isMultiShiping = false;
    private $isAdmin = false;
    private $i4goEndpoint = 'https://access.shift4test.com';

    //variables for loging and reports
    private $customerId = 0;
    private $orderId = 0;
    private $invoiceId;
    private $i4goType = '';
    protected $developerMode = false;

    private $transactionLog;
    private $customerSession;

    const HTML_INVOICE_FULL = 3;
    const HTML_INVOICE_SIMPLE = 2;
    const HTML_INVOICE_ORDER_NUMBER = 1;
    const HTML_INVOICE_NONE = 0;
    const MAX_REQUEST_SIZE = 3500;

    public function __construct(
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Shift4\Payment\Logger\Logger $logger,
        \Shift4\Payment\Logger\Debugger $shift4Debugger,
        \Magento\Framework\View\Result\PageFactory $pageFactory,
        \Magento\Backend\Model\Auth\Session $authSession,
        \Shift4\Payment\Model\TransactionLog $transactionLog,
        \Magento\Customer\Model\Session $customerSession
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->authSession = $authSession;
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
        $this->shift4Debugger = $shift4Debugger;
        $this->pageFactory = $pageFactory;
        $this->transactionLog = $transactionLog;
        $this->customerSession = $customerSession;

        if ($this->authSession->isLoggedIn()) {
            $this->session = $authSession;
            $this->isAdmin = true;
        } else {
            $this->session = $checkoutSession;
            if ($this->checkoutSession->getQuote()->getIsMultiShipping()) {
                $this->isMultiShiping = true;
            }
        }

        $this->verifySSL = $this->scopeConfig->getValue('payment/shift4/enable_ssl', ScopeInterface::SCOPE_STORE);

        if ($this->scopeConfig->getValue('payment/shift4/processing_mode', ScopeInterface::SCOPE_STORE) == 'live') {
            $this->endpoint = $this->scopeConfig
                ->getValue('payment/shift4/server_addresses', ScopeInterface::SCOPE_STORE);
            $this->accessToken = $this->scopeConfig
                ->getValue('payment/shift4/live_access_token', ScopeInterface::SCOPE_STORE);
            $this->i4goEndpoint = 'https://access.i4go.com';
            $this->verifySSL = true;
        }

        //logging
        if ($this->scopeConfig->getValue('payment/shift4/developer_mode', ScopeInterface::SCOPE_STORE) == 1) {
            $this->developerMode = true;
        }
    }

    public function setCustomerId($customerId)
    {
        $this->customerId = $customerId;
    }

    public function setOrderId($orderId)
    {
        $this->orderId = $orderId;
    }

    public function setInvoiceId($invoiceId)
    {
        $this->invoiceId = $invoiceId;
    }

    /**
     * Shift4 transaction
     *
     * @param float $amount
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param string $i4goTrueToken
     * @return array $response
     */
    public function transaction(
        $amount,
        $payment,
        $i4goTrueToken,
        $tax = 0,
        $i4goType = '',
        $transactionMode = '',
        $invoiceHtml = '',
        $productDescriptors = [],
        $iias = false,
        $invoiceId = false
    ) {
        $magentoOrderId = $payment->getOrder()->getIncrementId();
        $this->orderId = $magentoOrderId;
        $this->customerId = (int) $this->checkoutSession->getQuote()->getBillingAddress()->getCustomerId();
        $this->i4goType = $i4goType;

        if ($invoiceId) {
            $this->invoiceId = $invoiceId;
        }

        if ($transactionMode == '') {
            $method = 'authorization';
            $paymentAction = $this->scopeConfig->getValue('payment/shift4/payment_action', ScopeInterface::SCOPE_STORE);
            if ($paymentAction == 'authorize_capture') {
                $method = 'sale';
            }
        } else {
            $method = $transactionMode;
        }

        $billingAddress = $payment->getOrder()->getBillingAddress();

        $order = $payment->getOrder();

        if (empty($productDescriptors)) {
            $products = $payment->getOrder()->getAllItems();

            foreach ($products as $product) {
                if (!$product->getParentItem()) {
                    $productDescriptors[] = $product->getProduct()->getName();
                }
            }
        }

        $shift4Invoice = $this->shift4Invoice($payment);

        if (!$payment->getOrder()->getShippingAddress()) {
            $destinationPostalCode = $billingAddress->getPostCode();
        } else {
            $destinationPostalCode = $payment->getOrder()->getShippingAddress()->getPostCode();
        }

        $requestBody = [
            'dateTime' => date('c'),
            'amount' => [
                'tax' => $tax,
                'total' => $amount
            ],
            'clerk' => [
                'numericId' => $this->clerk
            ],
            'transaction' => [
                'invoice' => $shift4Invoice,
                'purchaseCard' => [
                    'customerReference' => $payment->getOrder()->getCustomerId(),
                    'productDescriptors' => $productDescriptors,
                    'destinationPostalCode' => $destinationPostalCode
                ]
            ],
            'customer' => [
                'firstName' => $billingAddress->getFirstName(),
                'lastName' => $billingAddress->getLastName(),
                'postalCode' => $billingAddress->getPostCode(),
                'addressLine1' => $billingAddress->getData('street'),
            ],
            'apiOptions' => [],
            'card' => [
                'token' => [
                    'value' => $i4goTrueToken
                ],
                'present' => 'N'
            ],
        ];

        //allow partial auth
        $allowPartialAuth = $this->scopeConfig
            ->getValue('payment/shift4/allow_partial_auth', ScopeInterface::SCOPE_STORE);
        if ($allowPartialAuth && !$this->isMultiShiping) {
            $requestBody['apiOptions'][] = 'ALLOWPARTIALAUTH';
        }

        if ((float) $this->session->getData('healthcareTotalAmount') > 0
            && $this->scopeConfig->getValue('payment/shift4/support_hsafsa', ScopeInterface::SCOPE_STORE)
        ) {
            $healthcareProducts = (array) $this->session->getData('healthcareProducts');
            $processedAmountHsaFsa = (float) $this->session->getData('processedAmountHsaFsa');
            $healthcareTotalAmount = (float) $this->session->getData('healthcareTotalAmount');

            $healthcareTotalAmount - $processedAmountHsaFsa;

            $requestBody['amount']['iiasAmounts'][] = [
                'type' => '4S',
                'amount' => $healthcareTotalAmount
            ];

            $requestBody['amount']['tax'] = (float) $this->session->getData('healthcareTax');

            $iiasAmounts = [];

            foreach ($healthcareProducts as $hsproduct) {
                $iiasAmounts[$hsproduct['iias_type']] = isset($iiasAmounts[$hsproduct['iias_type']])
                    ? $iiasAmounts[$hsproduct['iias_type']] + $hsproduct['price'] * $hsproduct['quantity']
                    : $hsproduct['price'] * $hsproduct['quantity'];
            }

            foreach ($iiasAmounts as $iiasType => $iiasAmount) {
                $type = substr($iiasType, 0, 2);

                if ($type != '4S') {
                    $requestBody['amount']['iiasAmounts'][] = [
                            'type' => $type,
                            'amount' => $iiasAmount
                    ];
                }
            }

            throw new \RuntimeException(print_r($requestBody, true));
//            print_r($requestBody);
//            die();
        }

        if ($invoiceHtml == '') {
            $htmlInvoice = $this->scopeConfig->getValue('payment/shift4/html_invoice', ScopeInterface::SCOPE_STORE);
            if ($htmlInvoice == self::HTML_INVOICE_FULL) {
                $pageObject = $this->pageFactory->create();
                $invoiceHtml = $pageObject->getLayout()
                    ->createBlock('Shift4\Payment\Block\Printinvoice')
                    ->setInformation($order)
                    ->getHTML();
            } elseif ($htmlInvoice == self::HTML_INVOICE_SIMPLE) {
                $pageObject = $this->pageFactory->create();
                $invoiceHtml = $pageObject->getLayout()
                    ->createBlock('Shift4\Payment\Block\Printinvoiceplain')
                    ->setInformation($order)
                    ->getHTML();
            } elseif ($htmlInvoice == self::HTML_INVOICE_ORDER_NUMBER) {
                $invoiceHtml = 'Order id: #'. $magentoOrderId;
            } else {
                $invoiceHtml = '';
            }
        }

        $requestBody['transaction']['notes'] = $invoiceHtml;

        if (strlen(json_encode($requestBody)) > self::MAX_REQUEST_SIZE) {
            $requestBody['transaction']['notes'] = '(Invoice was too large for this field) '
                . 'Order id: #'. $magentoOrderId;
        }

        $transCount = (int) $this->session->getData('transCount');
        $this->session->setData('transCount', $transCount+1);

        $response = $this->curlRequest('transactions/'.$method, $requestBody);

        return $response;
    }

    /**
     * Shift4 transaction refund
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @param string $invoice
     * @param string $i4goTrueToken
     * @return array $response
     */
    public function refund($payment, $amount, $invoice, $i4goTrueToken)
    {
        $billingAddress = $payment->getOrder()->getBillingAddress();
        $magentoOrderId = $payment->getOrder()->getRealOrderId();

        $this->orderId = $magentoOrderId;
        $this->customerId = $payment->getOrder()->getCustomerId();

        $refundMessage = __('Refund for order #%1', $magentoOrderId);

        if ($invoice != '') {
            $refundMessage .= __(', Shift4 invoice: %1', $invoice);
        }

        $shift4Invoice = $this->refundShift4Invoice($payment);

        $requestBody = [
            'dateTime' => date('c'),
            'amount' => [
             'total' => $amount
            ],
            'clerk' => [
                'numericId' => $this->clerk
            ],
            'transaction' => [
                'invoice' => $shift4Invoice,
                'notes' => $refundMessage
            ],
            'customer' => [
                'firstName' => $billingAddress->getFirstName(),
                'lastName' => $billingAddress->getLastName()
            ],
            'apiOptions' => [],
            'card' => [
                'token' => [
                    'value' => $i4goTrueToken
                ],
                'present' => 'N'
            ],
        ];

        $response = $this->curlRequest('transactions/refund', $requestBody);

        return $response;
    }

    /**
     * Shift4 transaction refund
     *
     * @param string $invoice
     * @param float $amount
     * @param string $i4goTrueToken
     * @return array $response
     */
    public function capture(
        $invoice,
        $amount,
        $i4goTrueToken,
        $tax,
        $orderId,
        $customerId,
        $magentoInvoice = false,
        $productDescriptors = [],
        $invoiceId = false
    ) {
        $this->orderId = $orderId;
        $this->customerId = $customerId;

        if ($invoiceId) {
            $this->invoiceId = $invoiceId;
        }

        $requestBody = [
            'dateTime' => date('c'),
            'amount' => [
                'tax' => $tax,
                'total' => $amount
            ],
            'clerk' => [
                'numericId' => $this->clerk
            ],
            'transaction' => [
                'invoice' => $invoice
            ],
            'apiOptions' => [],
            'card' => [
                'token' => [
                    'value' => $i4goTrueToken
                ]
            ],
        ];

        if (!empty($productDescriptors)) {
            $requestBody['transaction']['purchaseCard']['productDescriptors'] = $productDescriptors;
        }

        if ($magentoInvoice) {
            $requestBody['transaction']['notes'] = $magentoInvoice;

            if (strlen(json_encode($requestBody)) > self::MAX_REQUEST_SIZE) {
                $requestBody['transaction']['notes'] = '(Invoice was too large for this field) Order id: #'. $orderId;
            }
        }

        $response = $this->curlRequest('transactions/capture', $requestBody);

        return $response;
    }

    /**
     * Shift4 transaction refund
     *
     * @param string $invoice
     * @param float $amount
     * @param string $i4goTrueToken
     * @return array $response
     */
    public function update($invoice, $amount, $i4goTrueToken, $tax = 0, $transactionType = 'capture')
    {
        $requestBody = [
            'dateTime' => date('c'),
            'amount' => [
                'tax' => $tax,
                'total' => $amount
            ],
            'clerk' => [
                'numericId' => $this->clerk
            ],
            'transaction' => [
                'invoice' => $invoice
            ],
            'apiOptions' => [],
            'card' => [
                'token' => [
                    'value' => $i4goTrueToken
                ]
            ],
        ];

        $function = 'manualsale';

        if ($transactionType == 'authorization') {
            $function = 'manualauthorization';
        }

        $response = $this->curlRequest('transactions/'.$function, $requestBody);

        return $response;
    }

    /**
     * Shift4 get invoice
     *
     * @param string $invoice
     * @return array $response
     */
    public function getInvoice($invoice)
    {
        $headers = [
            'Content-Type: application/json',
            'CompanyName: '.$this->companyName,
            'AccessToken: '.$this->accessToken,
            'InterfaceName: '.$this->interfaceName,
            'InterfaceVersion: '.$this->interfaceVersion,
            'Invoice: '. $invoice,
        ];

        return $this->curlRequest('transactions/invoice', [], $headers, 'GET');
    }

    /**
     * Shift4 void invoice
     *
     * @param string $invoice
     * @return array $response
     */
    public function void($invoice)
    {
        $headers = [
            'Content-Type: application/json',
            'CompanyName: '.$this->companyName,
            'AccessToken: '.$this->accessToken,
            'InterfaceName: '.$this->interfaceName,
            'InterfaceVersion: '.$this->interfaceVersion,
            'Invoice: '. $invoice,
        ];

        return $this->curlRequest('transactions/invoice', [], $headers, 'DELETE');
    }

    /**
     * Get the access token for the Shift4 API requests
     *
     * @param String $authToken
     * @param String $serverAddresses
     *
     * @return array
     */
    public function getShift4AccessToken($authToken, $serverAddresses)
    {
        $array_server_Addresses = explode(',', $serverAddresses);
        $endPoint = $array_server_Addresses[0];

        if ($this->isEndpointValid($endPoint)) { //todo check
            $requestBody = [
                'dateTime' => date('c'),
                'credential' => [
                    'authToken' => $authToken,
                    'clientGuid' => $this->clientGuid,
                ]
            ];

            // MGO-146: Add the final slash to the UTG server, if it is missing.
            if (substr($endPoint, '-1') !== '/') {
                $endPoint .= '/';
            }

            $this->endpoint = $endPoint;

            $response = $this->curlRequest('credentials/accesstoken', $requestBody, [
                'Content-Type: application/json',
                'CompanyName: '.$this->companyName,
                'InterfaceName: '.$this->interfaceName,
                'InterfaceVersion: '.$this->interfaceVersion
            ]);

            return $response;
        } else {
            $data['error'] = true;
            $data['error_to_show_user'] = __('Not valid endpoint.');
            return $data;
        }
    }

     /**
      * Execute the API Request
      *
      * @param string $endPoint
      *
      * @return bool
      */
    private function isEndpointValid($endPoint)
    {
        return filter_var($endPoint, FILTER_VALIDATE_URL);
    }

    /**
     * curl request to Shift4 api
     *
     * @param String $url
     * @param array $requestBody
     * @param array $headers
     * @param string $customRequest
     *
     * @return array
     */
    private function curlRequest($url, $requestBody, $headers = [], $customRequest = 'POST')
    {
        if (empty($headers)) {
            $headers = [
                'Content-Type: application/json',
                'CompanyName: '.$this->companyName,
                'AccessToken: '.$this->accessToken,
                'InterfaceName: '.$this->interfaceName,
                'InterfaceVersion: '.$this->interfaceVersion
            ];
        }

        $cardNumber = $cardType = '';

        $return = [
            'HTTP Headers' => !empty($headers) ? '-H ' . implode(' -H ', $headers) : null,
            'URL' => $this->endpoint . $url,
            'Request Type' => 'POST',
            'errorMessage' => '',
            'http_code' => '',
            'error' => false,
            'data' => '',
            'primaryCode' => '',
            'invoice' => '',
        ];

        if (isset($requestBody['transaction'])
            && isset ($requestBody['transaction']['invoice'])
            && $requestBody['transaction']['invoice']) {
            $return['invoice'] = $requestBody['transaction']['invoice'];
        } else {
            foreach ($headers as $value) {
                if (substr($value, 0, 8) == 'Invoice:') {
                    $return['invoice'] = substr($value, 9);
                    break;
                }
            }
        }

        if (
            isset($requestBody['card'])
            && isset($requestBody['card']['token'])
            && isset($requestBody['card']['token']['value'])
            && $requestBody['card']['token']['value']
        ) {
            $cardNumber = 'XXXXXXXXXXXX' . substr($requestBody['card']['token']['value'], 0, 4);
        }

        $amount = 0;
        if (
            isset($requestBody['amount']['total'])
            && $requestBody['amount']
            && $requestBody['amount']['total']
        ) {
            $amount = (float) $requestBody['amount']['total'];
        }

        $jsonData = json_encode($requestBody);

        $handler = curl_init($this->endpoint . $url);
        curl_setopt($handler, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($handler, CURLOPT_TIMEOUT, 65);

        if ($customRequest == 'POST') {
            curl_setopt($handler, CURLOPT_POSTFIELDS, $jsonData);
            curl_setopt($handler, CURLOPT_POST, 1);
        }

        curl_setopt($handler, CURLOPT_CUSTOMREQUEST, $customRequest);
        curl_setopt($handler, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handler, CURLOPT_SSL_VERIFYPEER, $this->verifySSL);

        $response = curl_exec($handler);
        $httpCode = curl_getinfo($handler, CURLINFO_HTTP_CODE);
        $return['http_code'] = $httpCode;

        if (curl_errno($handler)) {

            $return['errorMessage'] = __('Server error');
            $return['error'] = curl_errno($handler) .' - '. curl_error($handler);
        } else {

            $return['data'] = $response;

            //with these http responses we will do later
            if ($httpCode != '200') {
                switch ($httpCode) {
                    case 403:
                        $return['errorMessage'] = __('ERROR -> 403 Forbidden');
                        $return['error'] = 403;
                        break;

                    case 400:
                        $return['errorMessage'] = __('ERROR -> 400 Bad Request');
                        $return['error'] = 400;
                        break;

                    case 404:
                        $return['errorMessage'] = __('ERROR -> 404 Not Found');
                        $return['error'] = 404;
                        break;

                    case 500:
                        $return['errorMessage'] = __('ERROR -> 500 Internal Server Error');
                        $return['error'] = 500;
                        break;

                    case 503:
                        $return['errorMessage'] = __('ERROR -> 503 Service Unavailable');
                        $return['error'] = 503;
                        break;

                    case 504:
                        $return['errorMessage'] = __('ERROR -> 504 Timed Out');
                        $return['error'] = 504;
                        break;

                    default:
                        $return['errorMessage'] = __('Request Error');
                        $return['error'] = $httpCode;
                        break;
                }

                if ($httpCode == 400 || $httpCode == 504) {
                    $responseData = json_decode($response);
                    if (json_last_error() == JSON_ERROR_NONE) {
                        $return['errorMessage'] = __(
                            "%1 Error: %2",
                            $httpCode,
                            $responseData->result[0]->error->longText
                        );
                    } else {
                        $return['errorMessage'] = __('Error');
                    }
                }
            } else {
                $return['data'] = $response;
            }
        }

        $logging = $this->scopeConfig->getValue('payment/shift4/logging', ScopeInterface::SCOPE_STORE);

        switch ($logging) {
            case 'problems':
                if ($return['error']) {
                    $this->logger->info($this->formatLogMessage($return, $jsonData));
                }
                break;

            case 'all':
                // log all the communication
                $this->logger->info($this->formatLogMessage($return, $jsonData));
                break;

            case 'off':
            // logging is off
        }

        $method = str_replace('transactions/', '', $url);

        if (!$this->customerId && !$this->isAdmin) {

            if ($this->customerSession->isLoggedIn()) {
                $this->customerId = $this->customerSession->getCustomer()->getId();
            }
        }

        if (!$this->invoiceId && ($method == 'sale' || $method == 'capture')) {
            $this->invoiceId = $this->transactionLog->getNextInvoiceId();
        }

        $saveData = [
            'amount' => $amount,
            'order_id' => $this->orderId,
            'invoice_id' => $this->invoiceId,
            'shift4_invoice' => $return['invoice'],
            'card_number' => $cardNumber,
            'card_type' => $this->i4goType,
            'customer_id' => $this->customerId,
            'transaction_mode' => $method,
            'timed_out' => ($return['error'] == 504 || $httpCode == 504 ? 1 : 0),
            'error' => $return['errorMessage'],
            'http_code' => $httpCode,
            'utg_request' => json_encode($requestBody),
            'request_header' => json_encode($headers),
            'utg_response' => $return['data']
        ];

        if ($this->customerId == 0 && !$this->isAdmin) { //workaround magento bug on guest user
            $guestUserTransactions = (array) $this->checkoutSession->getData('guestUserTransactions');
        }

        if ($customRequest == 'DELETE') {
            $saveData['voided'] = 1;
            $saveData['transaction_mode'] = 'void';

            if ($httpCode == '400' && $responseData->result[0]->error->primaryCode == '9815') {
                $saveData['error'] = 'Invoice Not Found';
            }

            if ($this->customerId == 0 && isset($guestUserTransactions[$return['invoice']]) && !$this->isAdmin) {
                foreach ($guestUserTransactions[$return['invoice']] as $k => $v) {
                    $guestUserTransactions[$return['invoice']][$k]['voided'] = 1;
                }
            } else {
                $this->transactionLog->updateTransaction(
                    $return['invoice'],
                    ['voided' => 1]
                );
            }
        } else {
            $responseData = json_decode($response);
            if (json_last_error() == JSON_ERROR_NONE) {
                $responseCode = $responseData->result[0]->transaction->responseCode ?? null;
                if ($responseCode && $responseCode != 'P') {
                    $responseError = $this->checkResponseForErrors($responseCode);
                    if ($responseError) {
                        $saveData['error'] = $responseError;
                        $saveData['voided'] = 1;
                    }

                }
            }
        }

        //additional logging
        $this->devLog('Shift4 writing log to reports db:' . json_encode($saveData));

        if ($this->customerId == 0 && !$this->isAdmin) { //workaround magento bug on guest user
            $guestUserTransactions[$saveData['shift4_invoice']][$method] = $saveData;
            $return['guestUserTransactions'][$saveData['shift4_invoice']][$method] = $saveData;
            $this->checkoutSession->setData('guestUserTransactions', $guestUserTransactions);
        } else {
            $this->transactionLog->saveTransaction($saveData);
        }

        return $return;
    }

    /**
     * generate Shift4 invoice number
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     *
     * @return string
     */
    public function shift4Invoice($payment)
    {
        $quoteId = $payment->getOrder()->getQuoteId();
        $quoteIdLast3 = str_pad(substr($quoteId, -3), 3, '0', STR_PAD_LEFT);
        return substr($payment->getOrder()->getIncrementId(), -5)
            . $quoteIdLast3
            . str_pad((int) $this->session->getData('transCount'), 2, '0', STR_PAD_LEFT);
    }

    /**
     * generate Shift4 invoice number for refund
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     *
     * @return string
     */
    public function refundShift4Invoice($payment)
    {
        $transCount = $refundTransCount = (int) $this->session->getData('refund_transCount');

        $serializer = new Serialize();
        $transactionsData = $payment->getData('shift4_additional_information');
        if ($transactionsData) {
            $transactions = $serializer->unserialize($transactionsData);
            $refundTransCount = $transCount + count($transactions);
        }
        $transCount++;

        $this->session->setData('refund_transCount', $transCount);

        $quoteId = $payment->getOrder()->getQuoteId();
        $quoteIdLast3 = str_pad(substr($quoteId, -3), 3, '0', STR_PAD_LEFT);
        return substr($payment->getOrder()->getIncrementId(), -5)
            . $quoteIdLast3
            . str_pad($refundTransCount, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Count transactions
     *
     * @param string $action
     * @return int
     */
    protected function transCount($action = 'increase')
    {
        switch ($action) {
            case 'increase':
                $transCount = (int) $this->session->getData('transCount');
                $transCount++;
                $this->session->setData('transCount', $transCount);
                break;
            case 'clear':
                $this->session->setData('transCount', 0);
                break;
            case 'return':
                return (int) $this->session->getData('transCount');
                break;
            default:
                break;
        }
    }

    /**
     * get Access Block for i4go iframe
     *
     * @return array
     */
     public function getAccessBlock($amount = 0, $magentoOrderId = 0)
    {
        $i4go_clientIp = $_SERVER['REMOTE_ADDR'];

        $return_data = [
            'error' => '',
            'i4go_server' => '',
            'i4go_accessblock' => '',
            'i4go_countrycode' => '',
            'i4go_i4m_url' => '',
        ];

        $i4goData = [
            'fuseaction' => 'account.preauthorizeClient',
            'i4go_clientip' => $i4go_clientIp,
            'i4go_accesstoken' => $this->accessToken
        ];

		//temp solution for 503 i4go error. We need to remove that after i4go will fix

		$enableGPay = $this->scopeConfig->getValue(
			'payment/shift4/enable_google_pay',
			\Magento\Store\Model\ScopeInterface::SCOPE_STORE
		);
		$enableAPay = $this->scopeConfig->getValue(
			'payment/shift4/enable_apple_pay',
			\Magento\Store\Model\ScopeInterface::SCOPE_STORE
		);

		if ($magentoOrderId != 0 && ($enableGPay || $enableAPay)) {
			$i4goData['i4go_basket'] = json_encode([
                'OrderDetails' => [
                    'OrderNumber' => $magentoOrderId,
                    'Amount' => $amount,
                    'CurrencyCode' => 'USD'
                ]
            ]);
		}

        $i4goData = str_replace('+', '%20', http_build_query($i4goData, '', '&'));

        $handler = curl_init();

        curl_setopt($handler, CURLOPT_URL, $this->i4goEndpoint);

        curl_setopt($handler, CURLOPT_POSTFIELDS, $i4goData);
        curl_setopt($handler, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($handler, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handler, CURLOPT_TIMEOUT, 65);
        curl_setopt($handler, CURLOPT_SSL_VERIFYPEER, $this->verifySSL);

        $response = curl_exec($handler);
        if (curl_errno($handler)) {
            $return_data['error'] = curl_error($handler);
        }

        $logging = $this->scopeConfig->getValue('payment/shift4/logging', ScopeInterface::SCOPE_STORE);

        if ($return_data['error'] == '') {
            $i4goResponse = json_decode($response);
            if (json_last_error()===JSON_ERROR_NONE) {
                if ($i4goResponse->i4go_response == 'SUCCESS') {
                    $return_data['i4go_server'] = $i4goResponse->i4go_server;
                    $return_data['i4go_accessblock'] = $i4goResponse->i4go_accessblock;
                    $return_data['i4go_countrycode'] = $i4goResponse->i4go_countrycode;
                    $return_data['i4go_i4m_url'] = $i4goResponse->i4go_i4m_url;

                    if ($logging == 'all') {
                        //$this->logger->info($this->formatLogMessage($i4goResponse, $i4goData)); //only errors
                    }
                } else {
                    $return_data['error'] = $i4goResponse->i4go_response;

                    if ($logging == 'problems' || $logging == 'all') {
                        $this->logger->info($this->formatLogMessage($i4goResponse, $i4goData));
                    }
                }
            } else {
                $return_data['error'] = 'Server error';
                $this->logger->info($this->formatLogMessage(['Error decoding JSON' => $response], $i4goData));
            }
        } elseif ($logging == 'problems' || $logging == 'all') {

            $this->logger->info($this->formatLogMessage($i4goResponse, $i4goData));
        }
        curl_close($handler);

        return $return_data;
    }

    /**
     * Format Log message
     *
     * @param array $return
     * @param array $request
     *
     * @return String
     */
    private function formatLogMessage($return, $request = '')
    {
        $logMessage = PHP_EOL;

        if ($request) {
            $logMessage .= 'Request: '. $request . PHP_EOL;
        }

        foreach ($return as $k => $v) {
            $logMessage .= $k .': '.$v . PHP_EOL;
        }

        $logMessage .= PHP_EOL;

        return $logMessage;
    }

    /**
     * Check transaction response for errors
     *
     * @param string $responseCode
     * @return string $error
     */
    public function checkResponseForErrors($responseCode)
    {
        $error = false;
        switch ($responseCode) {
            case 'A':
            case 'C':
                $error = false;
                break;
            case 'D':
                //declined
                $error = __('The transaction is declined.');
                break;
            case 'e':
                //error
                $error = __('Error response');
                break;
            case 'f':
                $error = __('An AVS or CSC failure has occurred.');
                break;
            case 'P':
                //partial
                $error = 'P';
                break;
            case 'R':
                $error = __('The transaction requires a voice referral.');
                break;
            default:
                //The approval status is unknown
                $error = __('Approval status is unknown.');
                break;
        }
        return $error;
    }

    public function devLog($message)
    {
        if ($this->developerMode) {
            $this->shift4Debugger->info($message);
        }
    }
}
