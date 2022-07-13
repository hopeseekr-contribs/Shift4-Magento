<?php

namespace Shift4\Payment\Model\ResourceModel;

class SavedCards extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{

    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('shift4_saved_cards', 'saved_card_id');
    }

    public function getCardsByCustomerId($customerId)
    {
        $table = $this->getMainTable();
        $where = $this->getConnection()->quoteInto(
            "customer_id = ?",
            $customerId
        );

        $sql = $this->getConnection()->select()->from($table)->where($where)->order('date', 'DESC');
        $id = $this->getConnection()->fetchAll($sql);
        return $id;
    }

    public function unsetDefaults($customerId)
    {
        $this->getConnection()->update(
            $this->getMainTable(),
            ['is_default' => 0],
            ['customer_id = ?' => (int)$customerId]
        );
    }

    public function deleteCard($customerId, $savedCardId)
    {
        return $this->getConnection()->delete(
            $this->getMainTable(),
            ['customer_id = ?' => (int)$customerId, 'saved_card_id = ?' => (int)$savedCardId]
        );
    }
}
