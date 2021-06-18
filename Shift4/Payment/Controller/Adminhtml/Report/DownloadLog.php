<?php

/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Shift4\Payment\Controller\Adminhtml\Report;

class DownloadLog extends \Magento\Backend\App\Action
{
    private $pageFactory;
    /**
     * Constructor
     *
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Shift4\Payment\Model\TransactionLog $transactionLog
    ) {
        parent::__construct($context);
        $this->transactionLog = $transactionLog;
    }

    /**
     * Load the page defined in view/adminhtml/layout/exampleadminnewpage_helloworld_index.xml
     *
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        
        $request['from'] = str_replace('-', '/', $this->getRequest()->getParam('from'));
        $request['to'] = str_replace('-', '/', $this->getRequest()->getParam('to'));
        $request['filter_type'] = $this->getRequest()->getParam('filter_type');
        $request['show_order_statuses'] = $this->getRequest()->getParam('show_order_statuses');
        $request['order_statuses'] = explode(',', $this->getRequest()->getParam('order_statuses'));
        $request['transaction_statuses'] = ['error', 'timedout', 'success', 'voided']; //no need filter transaction statuses in log file.
        $request['transaction_types'] = []; //no need filter transaction types in log file.
        
        $transactions = $this->transactionLog->getTransactions($request['from'], $request['to'], $request['filter_type'], $request['show_order_statuses'], $request['order_statuses'], $request['transaction_statuses'], $request['transaction_types'], false, 0, 900);
        
        $fileName = date('m-d-Y').'.log';
        
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename='.$fileName);
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        
        foreach ($transactions as $k => $v) {
            echo 'Transaction date: '.$v['transaction_date'].PHP_EOL;
            echo 'Method: '.$v['transaction_mode'].PHP_EOL;
            echo 'Request: '.stripslashes($v['utg_request']).PHP_EOL;
            echo 'Response: '.stripslashes($v['utg_response']).PHP_EOL;
            echo PHP_EOL . PHP_EOL;
        }
    }
}
