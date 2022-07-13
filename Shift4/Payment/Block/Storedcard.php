<?php

namespace Shift4\Payment\Block;

use Magento\Framework\View\Element\Template;

class Storedcard extends Template
{

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $session;
    protected $date;
    protected $customerstored;
    protected $currentCustomer;

    /**
     * @var \Magento\Variable\Model\VariableFactory
     */
    protected $variableVariableFactory;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Variable\Model\VariableFactory $variableVariableFactory,
        \Magento\Framework\Stdlib\DateTime\DateTime $dateTime,
        \Shift4\Payment\Model\SavedCards $savedCards,
        array $data = []
    ) {
        $this->session = $customerSession;
        $this->variableVariableFactory = $variableVariableFactory;
        $this->date = $dateTime;
        $this->customerstored = $savedCards;
        parent::__construct(
            $context,
            $data
        );
    }

    protected function _prepareLayout()
    {
    }

    /**
     * Get the customer stored cards
     *
     * @param null
     *
     * @return Shift4_Payment_Block_Customer_Storedcard
     */
    public function getStoredCards()
    {
        if (is_null($this->getData('stored_cards'))) {
            $customerId = $this->getCustomerId();

            if (!$customerId) {
                return null;
            }
            $collection = $this->customerstored->getCardsByCustomerId($customerId);
            $this->setData("stored_cards", $collection);
        }

        return $this->getData('stored_cards');
    }
   
    /**
     * Build 'Delete' URL
     *
     * @param Shift4_Payment_Model_Customerstored $storedCard
     *
     * @return string
     */
    public function getDeleteUrl($storedCard)
    {
        return $this->getUrl('*/*/deletecard', ['stored_card_id' => $storedCard->getId()]);
    }

    /**
     * Render block HTML
     *
     * @return string
     */
    protected function _toHtml()
    {
        $iframe = $this->getLayout()->createBlock('Shift4\Payment\Block\Iframe')
                ->setTemplate('i4go-iframe.phtml');
        $this->setChild('i4go_iframe', $iframe);
        return parent::_toHtml();
        
        /*
        $pager = $this->getIframeHtml();
        $this->setChild('i4go_iframe', $pager);
        $this->setFormId('addcardform');

        return parent::_toHtml();
        */
    }
}
