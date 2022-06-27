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
     * @param \Magento\Backend\App\Action\Context        $context
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

        $response = $this->getResponse()
            ->setHeader('Content-Type', 'application/octet-stream')
            ->setHeader('Content-Disposition', 'attachment; filename='.$fileName)
            ->setHeader('Expires', '0')
            ->setHeader('Cache-Control', 'must-revalidate')
            ->setHeader('Pragma', 'public');

        $body = '';
        foreach ($transactions as $k => $v) {
            $body .= 'Transaction date: '.$v['transaction_date'].PHP_EOL;
            $body .= 'Method: '.$v['transaction_mode'].PHP_EOL;
            $body .= 'Request: '.stripslashes($v['utg_request']).PHP_EOL;
            $body .= 'Response: '.stripslashes($v['utg_response']).PHP_EOL;
            $body .= PHP_EOL . PHP_EOL;
        }

        return $response->setBody($body);
    }
}
