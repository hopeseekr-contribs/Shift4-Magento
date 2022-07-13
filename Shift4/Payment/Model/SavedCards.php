<?php

namespace Shift4\Payment\Model;

use Magento\Framework\Exception\LocalizedException;

class SavedCards extends \Magento\Framework\Model\AbstractModel
{

    protected $messageManager;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $resource,
            $resourceCollection,
            $data
        );
        $this->messageManager = $messageManager;
    }

    protected function _construct()
    {
        parent::_construct();
        $this->_init('Shift4\Payment\Model\ResourceModel\SavedCards');
    }

    /**
     * Get cards by Customer Id
     *
     * @param integer $customerId
     * @return int $id
     */
    public function getCardsByCustomerId($customerId)
    {
        $id = $this->getResource()->getCardsByCustomerId($customerId);
        return $id;
    }

    /**
     * Delete card
     *
     * @param integer $customerId
     * @param integer $savedCardId
     * @return int $id
     */
    public function deleteCard($customerId, $savedCardId)
    {
        return $this->getResource()->deleteCard($customerId, $savedCardId);
    }

    /**
     * Save card
     *
     * @param int $customerId
     * @param string $i4goTrueToken
     * @param string $i4goExpYear
     * @param string $i4goType
     * @param string $i4goExpMonth
     * @param int $isDefault
     * @return string
     */
    public function saveCard($customerId, $i4goTrueToken, $i4goExpYear, $i4goType, $i4goExpMonth, $isDefault)
    {
        $customerId = (int) $customerId;

        if (!$this->validateCustomerId($customerId)) {
            throw new LocalizedException(__('Invalid Customer ID'));
        }
        
        if (!$this->validatei4goTrueToken($i4goTrueToken)) {
            throw new LocalizedException(__('Invalid Token'));
        }
        
        if (!$this->validatei4goExpYear($i4goExpYear)) {
            throw new LocalizedException(__('Invalid Exp Year'));
        }
        
        if (!$this->validatei4goType($i4goType)) {
            throw new LocalizedException(__('Invalid Card Type'));
        }
        
        if (!$this->validatei4goExpMonth($i4goExpMonth)) {
            throw new LocalizedException(__('Invalid Exp Month'));
        }
        
        $return = 1;
        
        $collection = $this->getCollection();
        $collection->addFieldToFilter('customer_id', $customerId)
                ->addFieldToFilter('cc_type', $i4goType)
                ->addFieldToFilter('last_four', $this->getLast4FromToken($i4goTrueToken))
                ->addFieldToFilter('cc_exp_month', $i4goExpMonth)
                ->addFieldToFilter('cc_exp_year', $i4goExpYear);
        if ($collection->count()) {
            $return = __('Already exist');
        } else {
            
            if ($isDefault) {
                $this->getResource()->unsetDefaults($customerId);
            }
            $this->setData([
                'customer_id' => $customerId,
                'cc_type' => $i4goType,
                'cc_exp_month' => $i4goExpMonth,
                'cc_exp_year' => $i4goExpYear,
                'last_four' => $this->getLast4FromToken($i4goTrueToken),
                'token' => $i4goTrueToken,
                'is_default' => $isDefault
            ]);
            $this->save();
        }
        return $return;
    }

    /**
     * Get Last 4 card numbers from token
     *
     * @param string $i4goTrueToken
     * @return string
     */
    public function getLast4FromToken($i4goTrueToken)
    {
        $last4 = '';

        //GTV token
        if (is_numeric($i4goTrueToken)) {
            $last4 = substr($i4goTrueToken, -4);
        
        //i4go truetoken
        } else {
            $last4 = substr($i4goTrueToken, 0, 4);
        }
        return $last4;
    }
    
    /**
     * validate Customer Id
     *
     * @param int $customerId
     * @return boolen
     */
    public function validateCustomerId($customerId)
    {
        return ($customerId > 0);
    }
    
    /**
     * validate i4go TrueToken
     *
     * @param int $i4goTrueToken
     * @return boolen
     */
    public function validatei4goTrueToken($i4goTrueToken)
    {
        return ($i4goTrueToken == filter_var($i4goTrueToken, FILTER_SANITIZE_SPECIAL_CHARS));
    }
    
    /**
     * validate i4go ExpYear
     *
     * @param int $i4goExpYear
     * @return boolen
     */
    public function validatei4goExpYear($i4goExpYear)
    {
        return (is_numeric($i4goExpYear) && strlen($i4goExpYear) == 4);
    }
    
    /**
     * validate i4go Card Type
     *
     * @param int $i4goType
     * @return boolen
     */
    public function validatei4goType($i4goType)
    {
        return in_array($i4goType, ['MC', 'VS', 'AX', 'DC', 'NS', 'JC', 'YC']);
    }

    /**
     * validate i4go Exp Month
     *
     * @param int $i4goExpMonth
     * @return boolen
     */
    public function validatei4goExpMonth($i4goExpMonth)
    {
        return (is_numeric($i4goExpMonth) && strlen($i4goExpMonth) <= 2);
    }
}
