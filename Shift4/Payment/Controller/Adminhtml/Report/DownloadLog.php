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
     * @var \Shift4\Payment\Model\TransactionLog 
     */
    private $transactionLog;

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
        $request['from'] = str_replace('-', '/', $this->getRequest()->getParam('from'));
        $request['to'] = str_replace('-', '/', $this->getRequest()->getParam('to'));
        $request['filter_type'] = $this->getRequest()->getParam('filter_type');
        $request['show_order_statuses'] = $this->getRequest()->getParam('show_order_statuses');
        $request['order_statuses'] = explode(',', $this->getRequest()->getParam('order_statuses'));
        // no need filter transaction statuses in log file.
        $request['transaction_statuses'] = ['error', 'timedout', 'success', 'voided'];
        // no need filter transaction types in log file.
        $request['transaction_types'] = [];

        $transactions = $this->transactionLog
            ->getTransactions(
                $request['from'],
                $request['to'],
                $request['filter_type'],
                $request['show_order_statuses'],
                $request['order_statuses'],
                $request['transaction_statuses'],
                $request['transaction_types'],
                false,
                0,
                900
            );

        $fileName = date('m-d-Y').'.log';

        $this->getResponse()
            ->setHeader('Content-Type', 'application/octet-stream')
            ->setHeader('Content-Disposition', 'attachment; filename='.$fileName)
            ->setHeader('Expires', '0')
            ->setHeader('Cache-Control', 'must-revalidate')
            ->setHeader('Pragma', 'public');

        $body = '';

        foreach ($transactions as $k => $v) {
            $body .= 'Transaction date: '.$v['transaction_date']."\n";
            $body .= 'Method: '.$v['transaction_mode']."\n";
            $body .= 'Request: '.stripslashes($v['utg_request'])."\n";
            $body .= 'Response: '.stripslashes($v['utg_response'])."\n";
            $body .= "\n" . "\n";
        }

        return $this->getResponse()->setBody($body);
    }
}
