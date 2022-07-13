<?php

namespace Shift4\Payment\Model\ResourceModel\SavedCards;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{

    /**
     * Resource initialization
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('Shift4\Payment\Model\SavedCards', 'Shift4\Payment\Model\ResourceModel\SavedCards');
    }
}
