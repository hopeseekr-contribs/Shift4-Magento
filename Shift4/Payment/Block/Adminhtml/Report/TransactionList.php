<?php

namespace Shift4\Payment\Block\Adminhtml\Report;

class TransactionList extends \Magento\Backend\Block\Template
{

    private $transactions;

    public function getTransactions()
    {
        return $this->transactions;
    }

    public function setTransactions($transactions)
    {
        $this->transactions = $transactions;
    }
}
