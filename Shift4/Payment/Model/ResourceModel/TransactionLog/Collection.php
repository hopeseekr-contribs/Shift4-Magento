<?php

namespace Shift4\Payment\Model\ResourceModel\TransactionLog;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{

    /**
     * Resource initialization
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('Shift4\Payment\Model\TransactionLog', 'Shift4\Payment\Model\ResourceModel\TransactionLog');
    }
}
