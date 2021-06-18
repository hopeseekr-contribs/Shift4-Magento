<?php

/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Shift4\Payment\Controller\Adminhtml\Report;

class DownloadTransactionLog extends \Magento\Backend\App\Action
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

    public function execute()
    {
        $logId = (int) $this->getRequest()->getParam('id');
        $transaction = $this->transactionLog->getTransaction($logId);
    
        $fileName = str_replace([' ', ':'], ['_', ''], $transaction['transaction_date']).'_'.$logId.'.log';
        
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename='.$fileName);
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        
        echo 'Transaction date: '.$transaction['transaction_date'].PHP_EOL;
        echo 'Method: '.$transaction['transaction_mode'].PHP_EOL;
        echo 'Request: '.stripslashes($transaction['utg_request']).PHP_EOL;
        echo 'Response: '.stripslashes($transaction['utg_response']).PHP_EOL;
    }
}
