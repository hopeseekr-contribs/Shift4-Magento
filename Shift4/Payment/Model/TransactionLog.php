<?php

namespace Shift4\Payment\Model;

class TransactionLog extends \Magento\Framework\Model\AbstractModel
{

    protected $messageManager;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $resource,
            $resourceCollection,
            $data
        );
        $this->messageManager = $messageManager;
    }

    protected function _construct()
    {
        parent::_construct();
        $this->_init('Shift4\Payment\Model\ResourceModel\TransactionLog');
    }

    public function saveTransaction($data)
    {
        $this->setData($this->formatInsertData($data));
        $this->save();
    }
    
    public function updateTransaction($shift4Invoice, $data)
    {
        $transactions = $this->getResource()->updateTransaction($shift4Invoice, $data);
    }
    
    public function getTransactions($from, $to, $filterType, $showOrderStatuses, $orderStatuses, $transactionStatuses, $transactionTypes, $countTotal = false, $lmitFrom = 0, $limitTo = 20)
    {
        $transactions = $this->getResource()->getTransactions($from, $to, $filterType, $showOrderStatuses, $orderStatuses, $transactionStatuses, $transactionTypes, $countTotal, $lmitFrom, $limitTo);
        return $transactions;
    }
    
    public function getTransaction($transactionId)
    {
        $transactions = $this->getResource()->getTransaction($transactionId);
        return $transactions;
    }
    
    public function getTransactionsByOrderId($orderId)
    {
        $transactions = $this->getResource()->getTransactionsByOrderId($orderId);
        return $transactions;
    }
    
    public function getTransactionsByInvoiceId($invoiceId)
    {
        $transactions = $this->getResource()->getTransactionsByInvoiceId($invoiceId);
        return $transactions;
    }
    
    public function getNextInvoiceId()
    {
        $invoiceId = $this->getResource()->getNextInvoiceId();
        return $invoiceId;
    }
    
    public function saveAllTransactions($transactions)
    {
        $insertData = [];
        foreach ($transactions as $invoice => $invoiceTransactions) {
            foreach ($invoiceTransactions as $method => $transacton) {
                $insertData[] = $this->formatInsertData($transacton);
            }
        }
        $this->getResource()->saveAllTransactions($insertData);
    }
    
    private function formatInsertData($data)
    {
        $cardType = $cardNumber = '';
            
        $timedOut = isset($data['timed_out']) ? (int) $data['timed_out'] : 0;
        $voided = isset($data['voided']) ? (int) $data['voided'] : 0;
        $error = isset($data['error']) ? $data['error'] : '';
        $amount = isset($data['amount']) ? (float) $data['amount'] : 0;
        $customerId = isset($data['customer_id']) ? (int) $data['customer_id'] : 0;
        $utgResponse = isset($data['utg_response']) ? $data['utg_response'] : '';
        $orderId = isset($data['order_id']) ? $data['order_id'] : '';
        $invoiceId = isset($data['invoice_id']) ? $data['invoice_id'] : '';
        $shift4Invoice = isset($data['shift4_invoice']) ? $data['shift4_invoice'] : '';
        $requestHeader = isset($data['request_header']) ? $data['request_header'] : [];
        $httpCode = isset($data['http_code']) ? $data['http_code'] : '';
        
        if ($utgResponse != '') {
            $responseJson = json_decode($utgResponse);
            if (json_last_error() == JSON_ERROR_NONE) {
                if (@$responseJson->result[0]->card->number) {
                    $cardNumber = @$responseJson->result[0]->card->number;
                }
                if (@$responseJson->result[0]->card->type) {
                    $cardType = @$responseJson->result[0]->card->type;
                }
            } else {
                if (!($data['card_type']) || $data['card_type'] == '' || !isset($data['card_number']) || $data['card_number'] == '') {
                    $data['card_number'] = $data['card_type'] = '';
                }
                $cardNumber = $data['card_number'];
                $cardType = $data['card_type'];
            }
        }
        
        $insertData = [
            'amount' => $amount,
            'card_type' => $cardType,
            'card_number' => $cardNumber,
            'order_id' => $orderId,
            'invoice_id' => $invoiceId,
            'shift4_invoice' => $shift4Invoice,
            'customer_id' => $customerId,
            'transaction_mode' => $data['transaction_mode'],
            'timed_out' => $timedOut,
            'voided' => $voided,
            'error' => $error,
            'http_code' => $httpCode,
            'utg_request' => $data['utg_request'],
            'request_header' => json_encode($requestHeader),
            'utg_response' => $utgResponse,
        ];

        return $insertData;
    }
}
