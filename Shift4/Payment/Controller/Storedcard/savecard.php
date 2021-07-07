<?php

namespace Shift4\Payment\Controller\Storedcard;

class Savecard extends \Magento\Framework\App\Action\Action
{

    protected $_modelFactory;
    protected $api;
    protected $customerSession;

    public function __construct(\Shift4\Payment\Model\Shift4 $shift4, \Magento\Customer\Model\Session $customerSession, \Magento\Framework\App\Request\Http $request, \Magento\Framework\App\Action\Context $context)
    {
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

        $i4goTrueToken = $this->getRequest()->getParam('i4goTrueToken');
        $i4goExpYear = $this->getRequest()->getParam('i4goExpYear');
        $i4goType = $this->getRequest()->getParam('i4goType');
        $i4goExpMonth = $this->getRequest()->getParam('i4goExpMonth');

        $customerId = $this->customerSession->getCustomer()->getId();

        if (!$customerId) {
            http_response_code(404);
            exit;
        }

        echo $this->shift4->saveCard($customerId, $i4goTrueToken, $i4goExpYear, $i4goType, $i4goExpMonth);
        exit;
    }
}
