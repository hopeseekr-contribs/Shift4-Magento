<?php

namespace Shift4\Payment\Block\Adminhtml\Report;

class Shift4 extends \Magento\Backend\Block\Template
{
    //default variables
    private $defaultFrom;
    private $totalRecords = 0;
    private $defaultTo;
    private $defaultShowOrderStatuses = 0;
    private $defaultFilterType = 'transaction_date';
    private $defaultOrderStatuses = [];
    private $defaultTransactionStatuses = ['error', 'timedout', 'success'];
    private $defaultTransactionTypes = ['refund', 'authorization', 'sale_capture'];
    private $defaultRowsPerPage = 20;
    private $totals = [];

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Sales\Model\Order\ConfigFactory $configFactory,
        \Magento\Backend\Helper\Data $backedHelper,
        \Shift4\Payment\Model\TransactionLog $transactionLog,
        \Magento\Framework\Pricing\Helper\Data $pricingHelper,
        array $data = []
    ) {
        $this->defaultFrom = date('m/d/Y', strtotime('-1 month'));
        $this->defaultTo = date('m/d/Y');
        $this->_configFactory = $configFactory;
        parent::__construct($context, $data);
        $this->backedHelper = $backedHelper;
        $this->transactionLog = $transactionLog;
        $this->pricingHelper = $pricingHelper;
    }

    public function getTransactionTotal()
    {

        $request = $this->getS4Request();

        $transactionsTotal = $this->transactionLog
            ->getTransactions(
                $request['from'],
                $request['to'],
                $request['filter_type'],
                $request['show_order_statuses'],
                $request['order_statuses'],
                $request['transaction_statuses'],
                $request['transaction_types'],
                true
            );
        return $transactionsTotal;
    }

    public function getS4Request()
    {

        $request = $this->backedHelper->prepareFilterString($this->getRequest()->getParam('filter'));
        $request['from'] = isset($request['from']) ? $request['from'] : $this->defaultFrom;
        $request['to'] = isset($request['to']) ? $request['to'] : $this->defaultTo;
        $request['filter_type'] = isset($request['filter_type']) ? $request['filter_type'] : $this->defaultFilterType;
        $request['show_order_statuses'] = isset($request['show_order_statuses'])
            ? $request['show_order_statuses']
            : $this->defaultShowOrderStatuses;
        $request['order_statuses'] = isset($request['order_statuses'])
            ? explode(',', $request['order_statuses'])
            : $this->defaultOrderStatuses;
        $request['transaction_statuses'] = isset($request['transaction_statuses'])
            ? explode(',', $request['transaction_statuses'])
            : $this->defaultTransactionStatuses;
        $request['transaction_types'] = isset($request['transaction_types'])
            ? explode(',', $request['transaction_types'])
            : $this->defaultTransactionTypes;

        return $request;
    }

    public function getTransactions()
    {

        $request = $this->getS4Request();

        $limit = $this->getLimit();
        $page = ($this->getPage() - 1) * $limit;

        $transactions = $this->transactionLog->getTransactions(
            $request['from'],
            $request['to'],
            $request['filter_type'],
            $request['show_order_statuses'],
            $request['order_statuses'],
            $request['transaction_statuses'],
            $request['transaction_types'],
            false,
            $page,
            $limit
        );

        $this->totalRecords = count($transactions);
        $orderTransactions = [];

        foreach ($transactions as $k => $v) {
            $amountProcessed = 0;

            $utgResponse = json_decode($v['utg_response']);
            if (json_last_error() == JSON_ERROR_NONE) {
				if (property_exists($utgResponse, 'result') && property_exists($utgResponse->result[0], 'amount')) {
					$amountProcessed = (float) $utgResponse->result[0]->amount->total;
				}
            }

            $transactions[$k]['order_url'] = (
            $v['order_id'] && $v['entity_id']
                ? $this->getUrl('sales/order/view', ['order_id' => $v['entity_id']])
                : ''
            );
            $transactions[$k]['amount_processed'] = $amountProcessed;
            $transactions[$k]['customer_url'] = (
            $v['customer_id'] ? $this->getUrl('customer/index/edit', [
                'id' => $v['customer_id']
            ]) : ''
            );
            $transactions[$k]['customer_firstname'] = (
            $v['customer_firstname'] ? $v['customer_firstname'] : $v['firstname']
            );
            $transactions[$k]['customer_lastname'] = (
            $v['customer_lastname'] ? $v['customer_lastname'] : $v['lastname']
            );
            $transactions[$k]['date_formated'] = $this->formatDate(
                $v['transaction_date'],
                \IntlDateFormatter::MEDIUM,
                true
            );
            $transactions[$k]['order_date_formated'] = $this->formatDate(
                $v['created_at'],
                \IntlDateFormatter::MEDIUM,
                true
            );

            switch ($request['filter_type']) {
                case 'order_date':
                    $transactions[$k]['download_url'] = $this->getUrl('payment/report/downloadorderlog', [
                        'id' => $v['entity_id']
                    ]);
                    $orderTransactions[$v['order_id']][] = $transactions[$k];
                    break;
                case 'shipping_date':
                    //todo
                    break;
                case 'timeout_date':
                case 'transaction_date':
                default:
                    $transactions[$k]['download_url'] = $this->getUrl('payment/report/downloadtransactionlog', [
                        'id' => $v['shift4_transaction_id']
                    ]);
                    $orderTransactions[$v['shift4_transaction_id']][] = $transactions[$k];
                    break;
            }
        }

        return $orderTransactions;
    }

    public function getLimit()
    {
        $limit = $this->getRequest()
            ->getParam('limit') ? $this->getRequest()->getParam('limit') : $this->defaultRowsPerPage;
        return $limit;
    }

    public function getPage()
    {
        return max(1, $this->getRequest()->getParam('page'));
    }

    public function formatPrice($price)
    {
        return $this->pricingHelper->currency($price, true, false);
    }

    public function getLogUrl()
    {
        $request = $this->getS4Request();
        $request['from'] = str_replace('/', '-', $request['from']);
        $request['to'] = str_replace('/', '-', $request['to']);
        $request['order_statuses'] = implode(',', $request['order_statuses']);
        return $this->getUrl('payment/report/downloadlog', $request);
    }

    public function getReportUrl()
    {
        return $this->getUrl('payment/report/index', ['filter' => $this->getRequest()->getParam('filter')]);
    }

    public function getOrderStatuses()
    {
        $orderConfig = $this->_configFactory->create();
        return $orderConfig->getStatuses();
    }
}
