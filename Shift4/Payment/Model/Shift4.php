<?php

namespace Shift4\Payment\Model;

use Magento\Framework\Exception\PaymentException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\StateException;
use Magento\Framework\View\Result\PageFactory;
use Magento\Quote\Api\ChangeQuoteControlInterface;
use Shift4\Payment\Exception\PartialPaymentException;

class Shift4 extends \Magento\Payment\Model\Method\AbstractMethod
{
    const CODE = 'shift4';
    const MODULE_NAME = 'Shift4_Payment';

    protected $_code = self::CODE;

    protected $api;
    protected $scopeConfig;
    protected $checkoutSession;
    protected $salesOrderFactory;
    protected $helper;
    protected $resultPageFactory;
    protected $healthcareTotalAmount = 0;
    protected $payment;
    protected $transactions;
    protected $partialProcessedAmounts;
    protected $invoice;
    protected $magentoInvoice = 0;
    protected $productDescriptors;

    /**
     * Is this payment method a gateway (online auth/charge) ?
     */
    protected $_isGateway = true;

    /**
     * Can authorize online?
     */
    protected $_canAuthorize = true;

    /**
     * Can capture funds online?
     */
    protected $_canCapture = true;
    /**
     * Can use this payment method in administration panel?
     */
    protected $_canUseInternal = true;

    /**
     * Can show this payment method as an option on checkout payment page?
     */
    protected $_canUseCheckout = true;
    protected $_canOrder = true;
    protected $_canCapturePartial = true;


    /**
     * Can refund online?
     */
    protected $_canRefund = true;

    /**
     * Can refund invoice refund online?
     */
    protected $_canRefundInvoicePartial = true;

    /**
     * Can void transactions online?
     */
    protected $_canVoid = true;
    
    /**
     * @var ChangeQuoteControlInterface $changeQuoteControl
     */
    private $changeQuoteControl;

    /**
     * @var QuoteManager $QuoteManager
     */
    private $quoteManager;
    
    /**
     * @var \Shift4\Payment\Logger\Logger
     */
    private $shift4Logger;
    
    private $productRepository;
    private $transactionLog;
    private $savedCards;
    private $customerSession;
    
    protected $developerMode = false;

    public function __construct(
        \Shift4\Payment\Model\Api $api,
        \Shift4\Payment\Helper\Data $helper,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Backend\Model\Auth\Session $authSession,
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Request\Http $request,
        \Magento\Sales\Model\OrderFactory $salesOrderFactory,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Backend\Model\Session\Quote $sessionQuote,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        PageFactory $resultPageFactory,
        \Magento\Payment\Model\Method\Logger $logger,
        \Shift4\Payment\Logger\Logger $shift4Logger,
        ChangeQuoteControlInterface $changeQuoteControl,
        \Magento\Persistent\Model\QuoteManager $quoteManager,
        \Magento\Catalog\Model\ProductRepository $productRepository,
        \Shift4\Payment\Model\TransactionLog $transactionLog,
        \Shift4\Payment\Model\SavedCards $savedCards,
        \Magento\Customer\Model\Session $customerSession,
        $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {

        parent::__construct($context, $registry, $extensionFactory, $customAttributeFactory, $paymentData, $scopeConfig, $logger, $resource, $resourceCollection, $data);

        $this->scopeConfig = $scopeConfig;
        $this->salesOrderFactory = $salesOrderFactory;
        $this->api = $api;
        $this->request = $request;
        $this->checkoutSession = $checkoutSession;
        $this->authSession = $authSession;
        $this->sessionQuote = $sessionQuote;
        $this->initializeData($data);
        $this->resultPageFactory = $resultPageFactory;
        $this->helper = $helper;
        $this->shift4Logger = $shift4Logger;
        $this->changeQuoteControl = $changeQuoteControl;
        $this->quoteManager = $quoteManager;
        $this->productRepository = $productRepository;
        $this->transactionLog = $transactionLog;
        $this->savedCards = $savedCards;
        $this->customerSession = $customerSession;
        
        if ($this->authSession->isLoggedIn()) {
            $this->session = $authSession;
        } else {
            $this->session = $checkoutSession;

            if ($this->checkoutSession->getQuote()->getBillingAddress()->getCustomerId() > 0 &&
                !$this->customerSession->isLoggedIn()
            ) {
                $this->customerSession->authenticate();
            }
        }

        //hsa/fsa
        if ($this->scopeConfig->getValue('payment/shift4/support_hsafsa', \Magento\Store\Model\ScopeInterface::SCOPE_STORE) == 1) {
        
            $healthcareProducts = [];

            if ($this->authSession->isLoggedIn()) {
                $products = $this->sessionQuote->getQuote()->getAllItems();
            } else {
                $products = $this->checkoutSession->getQuote()->getAllVisibleItems();
            }
            
            $healthcareTotalAmount = $healthcareTotalAmountWithTax = $healthcareTax = 0;
            
            foreach ($products as $product) {
    
                $productObject = $this->productRepository->getById($product->getProduct()->getId());
                
                if ($productObject->getAttribute('iias_type')) {
                    $hsaFsa = $productObject->getAttributeText('iias_type');

                    if ($hsaFsa) {

                        $price = $product->getPrice();
                        $priceInclTax = $product->getPriceInclTax();
                        $qty = $product->getQty();
                    
                        $healthcareProducts[] = [
                            'name' => $product->getProduct()->getName(),
                            'id' => $product->getProduct()->getId(),
                            'price' => $price,
                            'price_without_tax' => $product->getProduct()->getPriceInfo()->getPrice('final_price')->getAmount()->getBaseAmount(),
                            'quantity' => $qty,
                            'iias_type' => $productObject->getAttributeText('iias_type'),
                            'finalprice' => $priceInclTax,
                        ];
                        $healthcareTotalAmount += $qty * $price;
                        $healthcareTotalAmountWithTax += $qty * $priceInclTax;
                    }
                }
            }
                
            $this->session->setData('healthcareProducts', $healthcareProducts);
            $this->session->setData('healthcareTotalAmount', $healthcareTotalAmount);
            $this->session->setData('healthcareTotalAmountWithTax', $healthcareTotalAmountWithTax);
            $healthcareTax = $healthcareTotalAmountWithTax - $healthcareTotalAmount;
            $this->session->setData('healthcareTax', $healthcareTax);
            $this->healthcareTotalAmount = $healthcareTotalAmountWithTax;
            $this->healthcareTax = $healthcareTax;
            
        }
    }

    /**
     * Capture Payment.
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     */
    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        //if capture from admin invoice
        if ($payment->getData('shift4_additional_information')) {
            $transactions = unserialize($payment->getData('shift4_additional_information'));
            $errors = [];
            $order = $payment->getOrder();
            $orderId = $order->getIncrementId();
            $customerId = $order->getCustomerId();
            $firstInvoice = true;
            $this->invoice = null;
            $partialTransactions = $partialProcessedAmounts = [];
            $authorizations = $amountCaptured = $amountRemaining = $taxCaptured = $invoiceTax = 0;

            if ($order->hasInvoices()) {
            
                $oInvoiceCollection = $order->getInvoiceCollection();

                foreach ($oInvoiceCollection as $oInvoice) {
                    
                    if ($oInvoice->getIncrementId()) {
                        $firstInvoice = false;
                    } else {
                        $this->invoice = $oInvoice;
                        $invoiceTax = $oInvoice->getTaxAmount();
                    }
                }
            } else {
                $errors[] = 'No Invoice'; //todo: format
            }
            
            //print_r($transactions); die();
            
            if (isset($transactions['shift4_authorize_cards'])) { //order is from old version
            
                foreach ($transactions['shift4_authorize_cards'] as $k => $oldTransaction) {
                    if ($oldTransaction['transaction_type'] == 'Auth') {
                        
                        $transaction['preauthProcessedAmount'] = $oldTransaction['processed_amount'];
                        $transaction['uniqueId'] = $oldTransaction['i_4_go_true_token'];
                        $transaction['preauthInvoiceId'] = $oldTransaction['shift_4_invoice_id'];
                        $transaction['amountCaptured'] = 0;
                        $transaction['taxCaptured'] = 0;
                        $transaction['tax'] = (float) @$transactions['tax_amount'];

                        $authorizations++;
                        $partialTransactions[] = $transaction;
                        $this->partialProcessedAmounts[] = [
                            'amount' => $transaction['preauthProcessedAmount'],
                            'uniqueId' => $transaction['uniqueId'],
                            'preauthInvoiceId' => $transaction['preauthInvoiceId'],
                        ];
                        
                        $amountCaptured += @$transaction['amountCaptured'];
                        $taxCaptured += @$transaction['taxCaptured'];
                        $amountRemaining += @$transaction['preauthProcessedAmount'];
                        $lastTransaction = $transaction;
                    }
                }
                
            } else {
                
                foreach ($transactions as $k => $transaction) {
                    if ((isset($transaction['transactionType']) && ($transaction['transactionType'] == 'authorization' || $transaction['transactionType'] == 'capture')) || (isset($transaction['transaction_type']) && ($transaction['transaction_type'] == 'authorization' || $transaction['transaction_type'] == 'capture'))) {
                        if (@$transaction['frontend']) {
                            $authorizations++;
                            $partialTransactions[] = $transaction;
                            $this->partialProcessedAmounts[] = [
                                'amount' => $transaction['preauthProcessedAmount'],
                                'uniqueId' => $transaction['uniqueId'],
                                'preauthInvoiceId' => $transaction['preauthInvoiceId'],
                            ];
                        }
                        $amountCaptured += @$transaction['amountCaptured'];
                        $taxCaptured += @$transaction['taxCaptured'];
                        $amountRemaining += @$transaction['preauthProcessedAmount'];
                        $lastTransaction = $transaction;
                    }
                }
                
            }
            
            $amountRemaining = $amountRemaining - $amountCaptured;
            
            $this->payment = $payment;
            $this->transactions = $transactions;
            $successPayments = $shift4Invoices = [];

            $invoiceHTML = $this->getInvoiceHtml();

            
            $transactionsFromDb = $this->transactionLog->getTransactionsByOrderId($order->getId());
            
            $this->magentoInvoice = $this->transactionLog->getNextInvoiceId();
            
            $this->session->setData('transCount', count($transactionsFromDb)+1); //to keep unique invoice number for future transactions

            $productDescriptors = [];

            $items = $this->invoice->getAllItems();

            foreach ($items as $item) {
                if (!$item->getOrderItem()->getParentItem()) {
                    $productDescriptors[] = $item->getName();
                }
            }

            $this->productDescriptors = $productDescriptors;

            //no partial payment
            if ($authorizations == 1) {

                if ($amount == $amountRemaining && $amountCaptured == 0) { //one invoice
                    
                    $captureResponse = $this->doCapture($lastTransaction['preauthInvoiceId'], $amountRemaining, $lastTransaction['uniqueId'], $lastTransaction['tax'], $orderId, $customerId, $invoiceHTML);
                    if ($captureResponse['errors']) {
                        $errors = $captureResponse['errors'];
                    } else {
                        $shift4Invoices[] = $lastTransaction['preauthInvoiceId'];
                    }

                    
                } else {

                    if ($firstInvoice) { //first

                        $captureResponse = $this->doCapture($lastTransaction['preauthInvoiceId'], $amount, $lastTransaction['uniqueId'], $invoiceTax, $orderId, $customerId, $invoiceHTML);
                        if ($captureResponse['errors']) {
                            $errors = $captureResponse['errors'];
                        } else {
                            $shift4Invoices[] = $lastTransaction['preauthInvoiceId'];
                        }

                    } else { //second and others
                        
                        $captureResponse = $this->doSale($amount, $lastTransaction['uniqueId'], $invoiceTax, $invoiceHTML);
                        if ($captureResponse['errors']) {
                            $errors = $captureResponse['errors'];
                        } else {
                            $successPayments[] = $captureResponse['data']->result[0]->transaction->invoice;
                        }
                    }
                }
            } else { //partial payment

                if ($amount == $amountRemaining && $amountCaptured == 0) { //one invoice
                
                    foreach ($partialTransactions as $ptransaction) {
                        
                        $captureResponse = $this->doCapture($ptransaction['preauthInvoiceId'], $ptransaction['preauthProcessedAmount'], $ptransaction['uniqueId'], $ptransaction['tax'], $orderId, $customerId, $invoiceHTML);
                        if ($captureResponse['errors']) {
                            $errors = $captureResponse['errors'];
                        } else {
                        //    $successPayments[] = $ptransaction['preauthInvoiceId'];
                        }

                    }
                } else {

                    $amountCapturedLoop = $amountCaptured;

                    $remainingAmount = $amount;
                    
                    $requestingTax = 0;

                    foreach ($this->partialProcessedAmounts as $key => $proccessed) {
                        
                        $remainingInTransaction = $proccessed['amount'] - $amountCapturedLoop;

                        if ($remainingInTransaction <= 0) {    //this authorization transaction fully captured
                        
                            $amountCapturedLoop = $amountCapturedLoop - $proccessed['amount'];
                            continue;
                            
                        } elseif ($remainingAmount < $remainingInTransaction || abs($remainingAmount-$remainingInTransaction) < 0.00001) { //requested amount is less then remaining in this transaction
                            
                            $requestingTax = $invoiceTax - $requestingTax; //to avoid 1ct problem

                            if ($proccessed['amount'] == $remainingInTransaction) {
                                $captureResponse = $this->doCapture($proccessed['preauthInvoiceId'], $remainingAmount, $proccessed['uniqueId'], $requestingTax, $orderId, $customerId, $invoiceHTML);
                                if ($captureResponse['errors']) {
                                    $errors = $captureResponse['errors'];
                                }
                            } else {
                                $captureResponse = $this->doSale($remainingAmount, $proccessed['uniqueId'], $requestingTax, $invoiceHTML);
                                if ($captureResponse['errors']) {
                                    $errors = $captureResponse['errors'];
                                } else {
                                    $successPayments[] = $captureResponse['data']->result[0]->transaction->invoice;
                                }
                            }
                            
                            break;
                        } else { //remaining amount is more than remaining in this transaction
        
                            $remainingAmount = $remainingAmount - $remainingInTransaction;
                            $requestingTax = $this->calculateTaxPercentage($invoiceTax, $amount, $remainingInTransaction);

                            $amountCapturedLoop = 0;
                        
                            if ($proccessed['amount'] == $remainingInTransaction) {
                                $captureResponse = $this->doCapture($proccessed['preauthInvoiceId'], $remainingInTransaction, $proccessed['uniqueId'], $requestingTax, $orderId, $customerId, $invoiceHTML);
                                if ($captureResponse['errors']) {
                                    $errors = $captureResponse['errors'];
                                }
                            } else {
                                $captureResponse = $this->doSale($remainingInTransaction, $proccessed['uniqueId'], $requestingTax, $invoiceHTML);
                                if ($captureResponse['errors']) {
                                    $errors = $captureResponse['errors'];
                                } else {
                                    $successPayments[] = $captureResponse['data']->result[0]->transaction->invoice;
                                }
                            }
                            
                        }
                    }
                }
            }
            
            $payment->setData('shift4_additional_information', serialize($this->transactions));

            if ($errors) {
                $errorMessage = '';
                foreach ($errors as $invoice => $error) {
                    $errorMessage .= $invoice . ': '. (string) $error .'</br>';
                }
                $errorMessage = substr($errorMessage, 0, -5);
                
                //void all success payments
                foreach ($successPayments as $shift4InvoiceNr) {
                    $this->api->void($shift4InvoiceNr);
                }
                
                $this->transactionLog->getTransactionsByOrderId($order->getId());
                
                throw new PaymentException(__($errorMessage));
            }
        } else { //if directly from front page
            $this->shift4Transaction($amount, $payment);
        }

        return $this;
    }

    /**
     * Authorize a payment.
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     */
    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $this->shift4Transaction($amount, $payment);
        return $this;
    }

    /**
     * void payments
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @return void
     */
    public function void($payment)
    {
        if ($payment->getData('shift4_additional_information')) {

            $lastTransactionId = $payment->getTransactionId();
            
            $transactions = unserialize($payment->getData('shift4_additional_information'));
            $errors = [];

            foreach ($transactions as $k => $transaction) {

                if ($transaction['voided']) {
                    continue;
                }

                $response = $this->api->void($transaction['preauthInvoiceId']);

                if ($response['error']) {
                    $errors[$transaction['preauthInvoiceId']] = $response['errorMessage'];
                } else {

                    $data = json_decode($response['data']);
                    $responseCode = @$data->result[0]->transaction->responseCode;
                    $error = $this->checkResponseForErrors($responseCode);
                    
                    $parentTransactionId = explode('-', $lastTransactionId, 2);
                    $parentTransactionId = str_replace('-void', '', $parentTransactionId[1]);
                    
                    if (!$error) {

                        if ($lastTransactionId != $transaction['preauthInvoiceId'] . '-void' &&
                        $lastTransactionId != $transaction['preauthInvoiceId'] . '-authorization-void' &&
                        $lastTransactionId != $transaction['preauthInvoiceId'] . '-capture-void') {
                            $payment->setTransactionId($transaction['preauthInvoiceId'] . '-void');
                            $payment->setParentTransactionId($transaction['preauthInvoiceId'].'-'.$parentTransactionId);
                            $payment->setIsTransactionClosed(1);
                            $payment->addTransaction('void');
                        } else {
                            $payment->setTransactionId($transaction['preauthInvoiceId'] . '-void');
                            $payment->setParentTransactionId($transaction['preauthInvoiceId'].'-'.$parentTransactionId);
                            $payment->setIsTransactionClosed(1);
                        }
                        
                        $transactions[$k]['dateUpdated'] = $data->result[0]->dateTime;
                        $transactions[$k]['voided'] = 1;
                        $transactions[$k]['transaction_type'] = 'void';
                    } else {
                        $errors[$transaction['preauthInvoiceId']] = $error;
                    }
                }
            }

            $payment->setData('shift4_additional_information', serialize($transactions));
            
            if (!empty($errors)) {
                $errorMessage = '';
                foreach ($errors as $invoice => $error) {
                    $errorMessage .= $invoice . ': '. $error .'</br>';
                }
                $errorMessage = substr($errorMessage, 0, -5);
                throw new PaymentException(__($errorMessage));
            }
        }
    }
    
    /**
     * cancel payments
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @return void
     */
    public function cancel($payment)
    {
        $this->void($payment);
    }

    /**
     * refund a payment.
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return void
     */
    public function refund($payment, $amount)
    {
        if ($payment->getData('shift4_additional_information')) {

            $order = $payment->getOrder();
            $baseGrandTotal = $order->getBaseGrandTotal();
            $lastTransactionId = $payment->getTransactionId();

            $transactions = unserialize($payment->getData('shift4_additional_information'));
            
            $this->api->setCustomerId($order->getCustomerId());
            $this->api->setOrderId($order->getIncrementId());
            
            $invoiceId = $payment->getCreditmemo()->getInvoice()->getIncrementId();
            $this->api->setInvoiceId($invoiceId);

            if ($baseGrandTotal == $amount) {

                $errors = [];
                foreach ($transactions as $k => $transaction) {

                    if ($transaction['voided'] || $transaction['refunded']) {
                        continue;
                    }
                    
                    $response = $this->api->void($transaction['preauthInvoiceId']);
                    $getInvoiceData = json_decode($response['data']);

                    if ($response['error']) {

                        if ($response['primaryCode'] == '9815' || @$getInvoiceData->result[0]->error->primaryCode == '9815') { //batched

                            $response = $this->api->refund($payment, $transaction['preauthProcessedAmount'], $transaction['preauthInvoiceId'], $transaction['uniqueId']);
                            $data = json_decode($response['data']);
                            $responseCode = @$data->result[0]->transaction->responseCode;
                            $error = $this->checkResponseForErrors($responseCode);
                    
                            if (!$error) {
                                $refundInvoice = @$data->result[0]->transaction->invoice;
                                    
                                if ($lastTransactionId != $refundInvoice . '-refund' && $lastTransactionId != $transaction['preauthInvoiceId'] . '-capture-refund') { //workaround
                                    $payment->setTransactionId($refundInvoice . '-refund');
                                    $payment->setParentTransactionId($transaction['preauthInvoiceId']);
                                    $payment->addTransaction('refund');
                                    $payment->setIsTransactionClosed(1);
                                } else {
                                    $payment->setTransactionId($refundInvoice . '-refund');
                                    $payment->setParentTransactionId($transaction['preauthInvoiceId']);
                                }

                                $transactions[$refundInvoice]['cardType'] = $this->getCardFullName(@$data->result[0]->card->type);
                                $transactions[$refundInvoice]['preauthCardNumber'] = 'xxxx-' . substr(@$data->result[0]->card->number, -4);
                                $transactions[$refundInvoice]['preauthProcessedAmount'] = @$data->result[0]->amount->total;
                                $transactions[$refundInvoice]['preauthInvoiceId'] = $refundInvoice;
                                $transactions[$refundInvoice]['preauthAuthCode'] = @$data->result[0]->transaction->authorizationCode;
                                $transactions[$refundInvoice]['uniqueId'] = @$data->result[0]->card->token->value;
                                $transactions[$refundInvoice]['remainingAmount'] = 0;
                                $transactions[$refundInvoice]['cardCount'] = 0;
                                $transactions[$refundInvoice]['tax'] = 0;
                                $transactions[$refundInvoice]['response'] = $response['data'];
                                $transactions[$refundInvoice]['transaction_type'] = 'refund';
                                $transactions[$refundInvoice]['voided'] = 0;
                                $transactions[$refundInvoice]['refunded'] = 1;
                                $transactions[$refundInvoice]['date'] = @$data->result[0]->dateTime;
                                $transactions[$refundInvoice]['dateUpdated'] = '';
                            } else {
                                $errors[$transaction['preauthInvoiceId']] = $error;
                            }

                        //end refund
                        } else {
                            //unknown error
                            $errors[$transaction['preauthInvoiceId']] = $response['errorMessage'];
                        }
                    } else {
                        $data = json_decode($response['data']);
                        $responseCode = @$data->result[0]->transaction->responseCode;
                        $error = $this->checkResponseForErrors($responseCode);
                    
                        if (!$error) {
                            if ($lastTransactionId != $transaction['preauthInvoiceId'] . '-refund' && $lastTransactionId != $transaction['preauthInvoiceId'] . '-capture-refund') { //workaround
                                $payment->setTransactionId($transaction['preauthInvoiceId'] . '-void');
                                $payment->setParentTransactionId($transaction['preauthInvoiceId']);
                                $payment->addTransaction('refund');
                                $payment->setIsTransactionClosed(1);
                            } else {
                                $payment->setTransactionId($transaction['preauthInvoiceId'] . '-void');
                                $payment->setParentTransactionId($transaction['preauthInvoiceId']);
                            }
                            
                            $transactions[$k]['transaction_type'] = 'void';
                            $transactions[$k]['voided'] = 1;
                            $transactions[$k]['dateUpdated'] = @$data->result[0]->dateTime;
                        } else {
                            
                            $errors[$transaction['preauthInvoiceId']] = $error;
                        }
                    }
                }

            } else {
                $shift4Invoices = '';
                if (empty($transactions)) {
                    $error = 'no transactions';
                } else {
                    foreach ($transactions as $transaction) {
                        if ($transaction['voided'] || $transaction['refunded']) {
                            continue;
                        } else {
                            $currentTransaction = $transaction;
                        }
                        $shift4Invoices .= $currentTransaction['preauthInvoiceId'] . ', ';
                    }
                }

                $shift4Invoices = substr($shift4Invoices, 0, -2);

                $response = $this->api->refund($payment, $amount, $shift4Invoices, $currentTransaction['uniqueId']);
                $data = json_decode($response['data']);
                $responseCode = @$data->result[0]->transaction->responseCode;
                $error = $this->checkResponseForErrors($responseCode);

                if (!$error) {
                    $refundInvoice = @$data->result[0]->transaction->invoice;

                    $payment->setTransactionId($refundInvoice . '-refund');
                    $payment->setParentTransactionId($currentTransaction['preauthInvoiceId']);

                    $transactions[$refundInvoice]['cardType'] = $this->getCardFullName(@$data->result[0]->card->type);
                    $transactions[$refundInvoice]['preauthCardNumber'] = 'xxxx-' . substr(@$data->result[0]->card->number, -4);
                    $transactions[$refundInvoice]['preauthProcessedAmount'] = @$data->result[0]->amount->total;
                    $transactions[$refundInvoice]['preauthInvoiceId'] = $refundInvoice;
                    $transactions[$refundInvoice]['preauthAuthCode'] = @$data->result[0]->transaction->authorizationCode;
                    $transactions[$refundInvoice]['uniqueId'] = @$data->result[0]->card->token->value;
                    $transactions[$refundInvoice]['remainingAmount'] = 0;
                    $transactions[$refundInvoice]['cardCount'] = 0;
                    $transactions[$refundInvoice]['response'] = $response['data'];
                    $transactions[$refundInvoice]['transaction_type'] = 'refund';
                    $transactions[$refundInvoice]['voided'] = 0;
                    $transactions[$refundInvoice]['tax'] = 0;
                    $transactions[$refundInvoice]['refunded'] = 1;
                    $transactions[$refundInvoice]['date'] = @$data->result[0]->dateTime;
                    $transactions[$refundInvoice]['dateUpdated'] = '';
                } else {
                    $errors[$currentTransaction['preauthInvoiceId']] = $error;
                }
            }
            
            $payment->setData('shift4_additional_information', serialize($transactions));
            
            if (!empty($errors)) {
                $errorMessage = '';
                foreach ($errors as $invoice => $error) {
                    $errorMessage .= $invoice . ': '. $error .'</br>';
                }
                $errorMessage = substr($errorMessage, 0, -5);
                throw new PaymentException(__($errorMessage));
            }
        }
    }

    /**
     * Shift4 transaction
     *
     * @param float $amount
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @return array $response
     */
    private function shift4Transaction($amount, $payment)
    {

        $customerId = (int) $this->checkoutSession->getQuote()->getBillingAddress()->getCustomerId();

        // Prevent missing orders.
        if (!$this->changeQuoteControl->isAllowed($this->checkoutSession->getQuote())) {
            $this->api->devLog('Invalid state change detected. Customer ID: ' . $customerId . ' Order ID: ' . $payment->getOrder()->getIncrementId());

            //to set customer car to guest
            if ($customerId == 0) {
                $this->quoteManager->convertCustomerCartToGuest();
            }

            throw new PaymentException(__("Sorry, an error occurred. Please refresh the page and proceed with payment or wait for page to refresh automatically."));
        }
        
        //make sure not allow to do transaction with invalid session
        if ($customerId > 0 && !$this->customerSession->isLoggedIn()) {
            $this->api->devLog('Have Customer ID but no customer session: checkoutSession: ' . $customerId . ', Order ID: ' . $payment->getOrder()->getIncrementId());
            throw new PaymentException(__("Session Expired. Please Sign In again"));
        }

        $savedCardsEnabled = $hsaFsaCard = false;

        if ($customerId && $this->scopeConfig->getValue('payment/shift4/enable_saved_cards', \Magento\Store\Model\ScopeInterface::SCOPE_STORE)) {
            $savedCardsEnabled = true;
        }

        $transaction_type = 'authorization';
        if ($this->scopeConfig->getValue('payment/shift4/payment_action', \Magento\Store\Model\ScopeInterface::SCOPE_STORE) == 'authorize_capture') {
            $transaction_type = 'capture';
        }

        $processedAmount = (float) $this->session->getData('processedAmount');
        $amountTotal = $payment->getOrder()->getBaseGrandTotal();
        $tax = $payment->getOrder()->getTaxAmount();

        $this->api->devLog('Processed amount: '. $processedAmount .', Amount total: '. $amountTotal . ', tax: ' . $tax .', Customer Id: '. $customerId);

        //if partial authorization before.
        if ($processedAmount > 0) {
            $amount = $amount - $processedAmount;
            $tax = 0;
        }

        $saveCard = $i4goExpYear = $i4goType = $i4goExpMonth = 0;

        $requestRest = file_get_contents('php://input');
        
        $this->api->devLog('requestRest: '. $requestRest);

        $shift4_payment_request = json_decode($requestRest);

        if ($shift4_payment_request) {
            $i4goTrueToken = @$shift4_payment_request->paymentMethod->additional_data->i4goTrueToken;
            $saveCard = (int) @$shift4_payment_request->paymentMethod->additional_data->save_card;
            $i4goExpYear = @$shift4_payment_request->paymentMethod->additional_data->i4go_exp_year;
            $i4goExpMonth = @$shift4_payment_request->paymentMethod->additional_data->i4go_exp_month;
            $i4goType = @$shift4_payment_request->paymentMethod->additional_data->i4go_type;
        } elseif ($this->request->getParam('shift4truetoken')) {
            $i4goTrueToken = $this->request->getParam('shift4truetoken');
        } else {
            throw new PaymentException(__('Error getting i4go TrueToken'));
        }

        if (!ctype_alnum($i4goTrueToken)) {
            throw new PaymentException(__('Wrong i4go true token')); //just to be sure no xss
        }

        if ($i4goType && !ctype_alnum($i4goType)) {
            throw new PaymentException(__('Wrong Card Type')); //just to be sure no xss
        }

        //do transaction
        $response = $this->api->transaction($amount, $payment, $i4goTrueToken, $tax, $i4goType, '', '', []);

        $authorizedCardsData = (array) $this->session->getData('authorizedCardsData');

        if ($response['error'] == 504 && $response['invoice'] != '') { //timeout
        
            $this->api->devLog('got timeout');

            sleep(5); //todo: check mode and change in production mode to 3.
            $response = $this->api->getInvoice($response['invoice']);

            if ($response['error']) {
                throw new PaymentException($response['errorMessage']);
            } else {
                $data = json_decode($response['data']);
                if (@$data->result[0]->transaction->responseCode) {
                    $responseCode = $data->result[0]->transaction->responseCode;
                } else {
                    $responseCode = 'A';
                }

                if ($responseCode == 'A' && ($amount - @$data->result[0]->amount->total) > 0) {
                    $responseCode = 'P'; //partial. Not returned "P" when checking invoice
                }
            }
        } elseif ($response['error']) {
            throw new PaymentException($response['errorMessage']);
        } else {
            $data = json_decode($response['data']);
            $responseCode = @$data->result[0]->transaction->responseCode;
            if ($responseCode == 'A' && abs((float) $amount - (float) @$data->result[0]->amount->total) > 0.00001) { //todo check if not errors if this is partial payment
                $responseCode = 'P'; //partial.
            }
        }
        
        $remainingAmount = $amount - $data->result[0]->amount->total;
            
        $partialAuthData = [
            'requestedAmount' => isset($amount) ? $amount : null,
            'remainingAmount' => isset($remainingAmount) ? $remainingAmount : null,
            'cardType' => @$data->result[0]->card->type ? $this->getCardFullName(@$data->result[0]->card->type) : null,
            'preauthCardNumber' => @$data->result[0]->card->number ? 'xxxx-' . substr(@$data->result[0]->card->number, -4) : null,
            'preauthProcessedAmount' => @$data->result[0]->amount->total ? @$data->result[0]->amount->total : null,
            'uniqueId' => @$data->result[0]->card->token->value ? @$data->result[0]->card->token->value : null,
            'preauthInvoiceId' => @$data->result[0]->transaction->invoice ? @$data->result[0]->transaction->invoice : null,
            'preauthAuthCode' => @$data->result[0]->transaction->authorizationCode ? @$data->result[0]->transaction->authorizationCode : null,
            'cardCount' => $this->session->getData('transCount'),
            'transaction_type' => $transaction_type,
        ];
        
        $processedAmount = $processedAmount + $partialAuthData['preauthProcessedAmount'];
        $error = $this->checkResponseForErrors($responseCode);
        
        $this->api->devLog('responseCode: '. $responseCode .', checkResponseForErrors Result: ' . $error);

        if ((!$error || $error == 'P') && ($partialAuthData['preauthProcessedAmount'] < $amountTotal)) {
            //if last transaction than tax amount we will calculate different to avoid +-1ct error.
            if (!$error) {
                $taxBefore = 0;
                foreach ($authorizedCardsData as $k => $v) {
                    if ((isset($v['voided']) && $v['voided'] == 1) || (isset($v['refunded']) && $v['refunded'] == 1)) {
                        continue;
                    }
                    $taxBefore += $v['tax'];
                }
                $tax = $payment->getOrder()->getTaxAmount() - $taxBefore;
            } elseif ($error == 'P') { //if transaction not last
                $taxPayment = $payment->getOrder()->getTaxAmount();
                $tax = $this->calculateTaxPercentage($taxPayment, $amountTotal, $partialAuthData['preauthProcessedAmount']);
            }

            if ($tax > 0) {
                $this->api->update(@$data->result[0]->transaction->invoice, $partialAuthData['preauthProcessedAmount'], $i4goTrueToken, $tax, $transaction_type);
            }
            
            $this->api->devLog('got partial. Tax: ' . $tax);
        }

        $authorizedCardsData[$partialAuthData['preauthInvoiceId']]['cardType'] = $partialAuthData['cardType'];
        $authorizedCardsData[$partialAuthData['preauthInvoiceId']]['preauthCardNumber'] = $partialAuthData['preauthCardNumber'];
        $authorizedCardsData[$partialAuthData['preauthInvoiceId']]['preauthProcessedAmount'] = $partialAuthData['preauthProcessedAmount'];
        $authorizedCardsData[$partialAuthData['preauthInvoiceId']]['preauthInvoiceId'] = $partialAuthData['preauthInvoiceId'];
        $authorizedCardsData[$partialAuthData['preauthInvoiceId']]['preauthAuthCode'] = $partialAuthData['preauthAuthCode'];
        $authorizedCardsData[$partialAuthData['preauthInvoiceId']]['uniqueId'] = $partialAuthData['uniqueId'];
        $authorizedCardsData[$partialAuthData['preauthInvoiceId']]['remainingAmount'] = $partialAuthData['remainingAmount'];
        $authorizedCardsData[$partialAuthData['preauthInvoiceId']]['cardCount'] = $partialAuthData['cardCount'];
        $authorizedCardsData[$partialAuthData['preauthInvoiceId']]['response'] = $response['data'];
        $authorizedCardsData[$partialAuthData['preauthInvoiceId']]['transaction_type'] = $partialAuthData['transaction_type'];
        $authorizedCardsData[$partialAuthData['preauthInvoiceId']]['voided'] = 0;
        $authorizedCardsData[$partialAuthData['preauthInvoiceId']]['refunded'] = 0;
        $authorizedCardsData[$partialAuthData['preauthInvoiceId']]['frontend'] = 1; //identify that transaction from fronted
        $authorizedCardsData[$partialAuthData['preauthInvoiceId']]['tax'] = $tax;
        $authorizedCardsData[$partialAuthData['preauthInvoiceId']]['date'] = @$data->result[0]->dateTime;
        $authorizedCardsData[$partialAuthData['preauthInvoiceId']]['dateUpdated'] = '';
        

        if ($customerId == 0 && !$this->authSession->isLoggedIn()) { //workaround magento bug on guest user
            $guestUserTransactions = (array) $this->checkoutSession->getData('guestUserTransactions');
            if (!count($guestUserTransactions) && isset($response['guestUserTransactions'])) {
                $guestUserTransactions = $response['guestUserTransactions'];
            }
            $this->api->devLog('guest user triggers');
        }

        if (!$error) {
            //approved, redirect to success page after
            $payment->setData('shift4_additional_information', serialize($authorizedCardsData));

            if ($saveCard && $i4goExpMonth && $savedCardsEnabled) {

                if (!ctype_alnum($i4goExpYear)) {
                    throw new PaymentException(__('Wrong Exp Year')); //just to be sure no xss
                }
                        
                if (!ctype_alnum($i4goExpMonth)) {
                    throw new PaymentException(__('Wrong Exp Month')); //just to be sure no xss
                }

                $this->saveCard($customerId, $i4goTrueToken, $i4goExpYear, $i4goType, $i4goExpMonth, 1);
            }

            $key = 1;
            $last_trans_id='';

            foreach ($authorizedCardsData as $transaction) {
                $trType = $transaction_type;

                $updateData = [
                    'customer_id' => (int) $customerId,
                    'order_id' => $payment->getOrder()->getRealOrderId()
                ];
                
                if ($transaction['voided'] == 1) {
                    $trType = 'void';
                    $updateData['voided'] = '1';
                }
                if ($last_trans_id == '') {
                    $last_trans_id = $trType;
                }

                if ($transaction_type == 'authorization') {

                    $payment->addTransaction($last_trans_id);
                    $payment->setIsTransactionClosed(0);
                    $payment->setTransactionId($transaction['preauthInvoiceId'].'-'.$trType);
                    $last_trans_id = $trType;

                } else {

                    $payment->setIsTransactionClosed(0);
                    $payment->setTransactionId($transaction['preauthInvoiceId'].'-'.$trType);
                    $payment->addTransaction($trType);
                }

                $key++;

                if ($customerId == 0 && !$this->authSession->isLoggedIn()) {

                    if (array_key_exists($transaction['preauthInvoiceId'], $guestUserTransactions) && is_array($guestUserTransactions[$transaction['preauthInvoiceId']])) {
                        foreach ($guestUserTransactions[$transaction['preauthInvoiceId']] as $k => $v) {
                            $guestUserTransactions[$transaction['preauthInvoiceId']][$k]['order_id'] = $updateData['order_id'];
                            $guestUserTransactions[$transaction['preauthInvoiceId']][$k]['voided'] = (int) $transaction['voided'];
                        }
                    } else {
                        $this->api->devLog('$guestUserTransactions not found. Customer ID: ' . $customerId . ' Order ID: ' . $updateData['order_id'] . 'Voided: ' . (int) $transaction['voided'] . 'Invoice ID: ' . $transaction['preauthInvoiceId']);
                    }
                } else {
                    $this->transactionLog->updateTransaction(
                        $transaction['preauthInvoiceId'],
                        $updateData
                    );
                }
            }

            if ($customerId == 0 && !$this->authSession->isLoggedIn()) {
                $this->transactionLog->saveAllTransactions($guestUserTransactions);
            }

            $this->session->setData('authorizedCardsData', []);
            $this->session->setData('processedAmount', 0);
            $this->session->setData('processedAmountHsaFsa', 0);
            $this->session->setData('transCount', 0);
            $this->session->setData('healthcareProducts', []);
            $this->session->setData('healthcareTotalAmount', 0);
            $this->session->setData('guestUserTransactions', []);
            $this->session->setData('healthcareTotalAmountWithTax', 0);
            $this->session->setData('healthcareTax', 0);
            $this->api->devLog('Approved code ends. saveCard: '. $saveCard);
        } elseif ($error == 'P') {
         //A partial authorization has occurred.
            $partialAuthData['partialPayment'] = 1;
            $partialAuthData['message'] = __('A partial payment for this order has been made. Please pay the remaining amount.');

            $this->session->setData('authorizedCardsData', $authorizedCardsData);
            $this->session->setData('processedAmount', $processedAmount);
            
            if ($hsaFsaCard) { //now disabled
                $processedAmountHsaFsa = $processedAmountHsaFsa + $processedAmount;
                $partialAuthData['hsafsa'] = 1;
                $this->session->setData('processedAmountHsaFsa', $processedAmountHsaFsa);
            }
            
            $this->api->devLog('Partial authorization');

            if (!$this->authSession->isLoggedIn()) { //if customer

                if ($saveCard && $i4goExpMonth && $savedCardsEnabled) {
                    $this->saveCard(
                        $this->checkoutSession->getQuote()->getBillingAddress()->getCustomerId(),
                        $i4goTrueToken,
                        $i4goExpYear,
                        $i4goType,
                        $i4goExpMonth
                    );
                }
                throw new PartialPaymentException($partialAuthData, __('A partial payment for this order has been made. Please pay the remaining amount.'));

            } else { //if admin
                throw new PaymentException(__('A partial payment for $%1 for this order has been made. Please pay the remaining amount ($%2) or cancel partial payments.', $partialAuthData['preauthProcessedAmount'], $partialAuthData['remainingAmount']));
            }
        } else {
            $this->api->devLog('Error in response' . $error);
            if ($response['invoice'] != '') {
                if ($customerId == 0 && !$this->authSession->isLoggedIn()) {
                    foreach ($guestUserTransactions[$response['invoice']] as $k => $v) {
                        $guestUserTransactions[$response['invoice']][$k]['error'] = $error;
                        $guestUserTransactions[$response['invoice']][$k]['voided'] = 1;
                    }
                    $this->checkoutSession->setData('guestUserTransactions', $guestUserTransactions);
                } else {
                    $this->transactionLog->updateTransaction(
                        $response['invoice'],
                        [
                            'voided' => 1,
                            'customer_id' => $customerId,
                            'error' => $error
                        ]
                    );
                }
            }
            throw new PaymentException($error);
        }

        $this->transactionLog = null; //close conections
        
        $this->api->devLog('Shift4 completed.');
        return $response;
    }

    /**
     * getCardFullName
     *
     * @param string $cardType
     * @return string $cardFullName
     */
    protected function getCardFullName($cardType = null)
    {
        $cardFullName = null;

        if (isset($cardType)) {
            switch ($cardType) {
                case 'MC':
                    $cardFullName = 'MasterCard';
                    break;
                case 'VS':
                    $cardFullName = 'Visa';
                    break;
                case 'AX':
                    $cardFullName = 'American Express';
                    break;
                case 'DC':
                    $cardFullName = 'Diners Club';
                    break;
                case 'NS':
                    $cardFullName = 'Discover';
                    break;
                case 'JC':
                    $cardFullName = 'JCB';
                    break;
                case 'YC':
                    $cardFullName = 'Gift Card';
                    break;
                default:
                    $cardFullName = $cardType;
                    break;
            }
        }
        return $cardFullName;
    }

    /**
     * Cancels all partial payments
     * @return string
     */
    public function cancelAllPartialPayments()
    {
        $authorizedCardsData = (array) $this->session->getData('authorizedCardsData');
        $errorMessage = '';

        foreach ($authorizedCardsData as $k => $v) {
            if (!$v['voided']) {

                $response = $this->api->void($v['preauthInvoiceId']);

                if (!$response['error'] || $response['error'] == '') {
                    $data = json_decode($response['data']);
                    $authorizedCardsData[$k]['voided'] = 1;
                    $authorizedCardsData[$k]['transaction_type'] = 'void';
                    $authorizedCardsData[$k]['dateUpdated'] = @$data->result[0]->dateTime;
                } else {
                    $errorMessage .= __("%1 not voided. Error: %2", $k, $response['errorMessage']).'<br>';
                }
            }
        }
        if (!$errorMessage) {

            $this->session->setData('authorizedCardsData', $authorizedCardsData);
            $this->session->setData('processedAmount', 0);
            $this->session->setData('processedAmountHsaFsa', 0);

            return 1;
        } else {
            return $errorMessage;
        }
    }

    /**
     * Cancels all partial payment
     *
     * @param string $invoiceId
     * @return string
     */
    public function cancelPartialPayment($invoiceId)
    {
        $authorizedCardsData = (array) $this->session->getData('authorizedCardsData');

        if (isset($authorizedCardsData[$invoiceId])) {
            $processedAmount =    $this->session->getData('processedAmount');
            
            $processedAmount = $processedAmount - $authorizedCardsData[$invoiceId]['preauthProcessedAmount'];
            
            $response = $this->api->void($invoiceId);
            if (!$response['error'] || $response['error'] == '') {
                $data = json_decode($response['data']);
                
                $authorizedCardsData[$invoiceId]['voided'] = 1;
                $authorizedCardsData[$invoiceId]['transaction_type'] = 'void';
                $authorizedCardsData[$invoiceId]['dateUpdated'] = @$data->result[0]->dateTime;
                $this->session->setData('authorizedCardsData', $authorizedCardsData);
                $this->session->setData('processedAmount', $processedAmount);
                return 1;
            } else {
                return $response['errorMessage'];
            }
        } else {
            return __('Invoice not found.');
        }
    }

    /**
     * Save card
     *
     * @param int $customerId
     * @param string $i4goTrueToken
     * @param string $i4goExpYear
     * @param string $i4goType
     * @param string $i4goExpMonth
     * @param int $isDefault
     * @return string
     */
    public function saveCard($customerId, $i4goTrueToken, $i4goExpYear, $i4goType, $i4goExpMonth, $isDefault = 0)
    {
        return $this->savedCards->saveCard($customerId, $i4goTrueToken, $i4goExpYear, $i4goType, $i4goExpMonth, $isDefault);
    }

    /**
     * Delete card
     *
     * @param int $customerId
     * @param int $savedCardId
     * @return void
     */
    public function deleteCard($customerId, $savedCardId)
    {
        return $this->savedCards->deleteCard($customerId, $savedCardId);
    }

    /**
     * Check transaction response for errors
     *
     * @param string $responseCode
     * @return string $error
     */
    private function checkResponseForErrors($responseCode)
    {
        return $this->api->checkResponseForErrors($responseCode);
    }
    
    private function calculateTaxPercentage($taxTotal, $totalAmount, $transactionAmount)
    {
        $percent = ($transactionAmount / $totalAmount) * 100;
        $tax = round((($taxTotal / 100) * $percent), 2);
        return $tax;
    }
    
    private function getInvoiceHtml()
    {
        
        if ($this->scopeConfig->getValue('payment/shift4/html_invoice', \Magento\Store\Model\ScopeInterface::SCOPE_STORE) == $this->api::HTML_INVOICE_FULL) {
            $pageObject = $this->resultPageFactory->create();
            $invoiceHtml = $pageObject->getLayout()
                ->createBlock('Shift4\Payment\Block\Printinvoice')
                ->setInformation($this->payment->getOrder(), $this->invoice)
                ->getHTML();
        } elseif ($this->scopeConfig->getValue('payment/shift4/html_invoice', \Magento\Store\Model\ScopeInterface::SCOPE_STORE) == $this->api::HTML_INVOICE_SIMPLE) {
            $pageObject = $this->resultPageFactory->create();
            $invoiceHtml = $pageObject->getLayout()
                ->createBlock('Shift4\Payment\Block\Printinvoiceplain')
                ->setInformation($this->payment->getOrder(), $this->invoice)
                ->getHTML();
        } elseif ($this->scopeConfig->getValue('payment/shift4/html_invoice', \Magento\Store\Model\ScopeInterface::SCOPE_STORE) == $this->api::HTML_INVOICE_ORDER_NUMBER) {
            $invoiceHtml = 'Order id: #'. $this->payment->getOrder()->getIncrementId();
        } else {
            $invoiceHtml = '';
        }
        
        
        return $invoiceHtml;
    }

    private function doCapture($invoiceId, $amount, $i4goTrueToken, $tax, $orderId, $customerId, $currentInvoice = false)
    {

        $errors = $data = [];
        $response = $this->api->capture($invoiceId, $amount, $i4goTrueToken, $tax, $orderId, $customerId, $currentInvoice, $this->productDescriptors, false, $this->magentoInvoice);
        if ($response['error']) {
            $errors[$invoiceId] = $response['errorMessage'];
        } else {
            $data = json_decode($response['data']);
            $responseCode = @$data->result[0]->transaction->responseCode;
            $error = $this->checkResponseForErrors($responseCode);

            if (!$error) {

                $this->payment->unsLastTransId();
                $this->payment->setTransactionId($invoiceId . '-capture');
                $this->payment->setParentTransactionId($invoiceId);
                $this->payment->setIsTransactionClosed(1);
                $this->payment->addTransaction('capture');
                $this->payment->unsLastTransId();

                $this->transactions[$invoiceId]['transaction_type'] = 'capture';
                $this->transactions[$invoiceId]['amountCaptured'] = @$data->result[0]->amount->total;
                $this->transactions[$invoiceId]['taxCaptured'] = $tax;
                $this->transactions[$invoiceId]['dateUpdated'] = @$data->result[0]->dateTime;

            } else {
                $errors[$invoiceId] = $error;
            }
        }
        return ['errors' => $errors, 'data' => $data];
    }
    
    private function doSale($amount, $i4goTrueToken, $tax, $invoiceHTML)
    {

        $errors = $data = [];

        $response = $this->api->transaction($amount, $this->payment, $i4goTrueToken, $tax, '', 'sale', $invoiceHTML, $this->productDescriptors, false, $this->magentoInvoice);
        $invoiceNr = $response['invoice'];
        
        if ($response['error']) {
            $errors[$invoiceNr] = $response['errorMessage'];
        } else {
            $data = json_decode($response['data']);
            $responseCode = @$data->result[0]->transaction->responseCode;
            $error = $this->checkResponseForErrors($responseCode);

            if (!$error) {
                $this->transactions[$invoiceNr]['cardType'] = $this->getCardFullName(@$data->result[0]->card->type);
                $this->transactions[$invoiceNr]['preauthCardNumber'] = 'xxxx-' . substr(@$data->result[0]->card->number, -4);
                $this->transactions[$invoiceNr]['preauthProcessedAmount'] = @$data->result[0]->amount->total;
                $this->transactions[$invoiceNr]['preauthInvoiceId'] = $invoiceNr;
                $this->transactions[$invoiceNr]['preauthAuthCode'] = @$data->result[0]->transaction->authorizationCode;
                $this->transactions[$invoiceNr]['uniqueId'] = @$data->result[0]->card->token->value;
                $this->transactions[$invoiceNr]['remainingAmount'] = 0;
                $this->transactions[$invoiceNr]['cardCount'] = 0;
                $this->transactions[$invoiceNr]['tax'] = $tax;
                $this->transactions[$invoiceNr]['response'] = $response['data'];
                $this->transactions[$invoiceNr]['transaction_type'] = 'refund';
                $this->transactions[$invoiceNr]['voided'] = 0;
                $this->transactions[$invoiceNr]['refunded'] = 0;
                $this->transactions[$invoiceNr]['date'] = @$data->result[0]->dateTime;
                $this->transactions[$invoiceNr]['frontend'] = 0;
                $this->transactions[$invoiceNr]['transaction_type'] = 'capture';
                $this->transactions[$invoiceNr]['amountCaptured'] = $amount;
                $this->transactions[$invoiceNr]['taxCaptured'] = $tax;
                $this->transactions[$invoiceNr]['dateUpdated'] = @$data->result[0]->dateTime;

            } else {
                $errors[$invoiceNr] = $error;
            }
        }
        return ['errors' => $errors, 'data' => $data];
    }
}
