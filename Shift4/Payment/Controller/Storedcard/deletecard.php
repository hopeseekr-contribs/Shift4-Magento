<?php

namespace Shift4\Payment\Controller\Storedcard;

class Deletecard extends \Magento\Framework\App\Action\Action
{

    protected $_modelFactory;
    protected $api;
    protected $customerSession;

    public function __construct(
        \Shift4\Payment\Model\Shift4 $shift4,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\App\Request\Http $request,
        \Magento\Framework\App\Action\Context $context
    ) {
        $this->shift4 = $shift4;
        $this->request = $request;
        $this->customerSession = $customerSession;
        parent::__construct(
            $context
        );
    }

    /**
     * cancel preauthorized amount.
     *
     * @return Int
     */
    public function execute()
    {

        $savedCardId = $this->getRequest()->getParam('saved_card_id');

        $customerId = $this->customerSession->getCustomer()->getId();

        if (!$customerId) {
            throw new \InvalidArgumentException('No customerId was given.');
        }

        $deletecard = $this->shift4->deleteCard($customerId, $savedCardId);
        if ($deletecard) {
            $body = 1;
        } else {
            $body = $deletecard;
        }

        $this->getResponse()->setBody($body);
    }
}
