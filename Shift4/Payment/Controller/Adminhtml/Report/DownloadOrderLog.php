<?php

/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Shift4\Payment\Controller\Adminhtml\Report;

class DownloadOrderLog extends \Magento\Backend\App\Action
{
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
        $orderId = (int) $this->getRequest()->getParam('id');
        $transactions = $this->transactionLog->getTransactionsByOrderId($orderId);
    
        $fileName = str_replace([' ', ':'], ['_', ''], $transactions[0]['created_at']).'_'.$orderId.'.log';
        
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
