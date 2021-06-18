<?php

namespace Shift4\Payment\Controller\Storedcard;

use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\App\Action\Context;

class Index extends \Magento\Framework\App\Action\Action
{

    protected $pageFactory;

    public function __construct(
        Context $context,
        PageFactory $pageFactory,
        \Magento\Customer\Model\Session $session
    ) {
        $this->pageFactory = $pageFactory;
        parent::__construct($context);
        $this->session = $session;
    }

    public function execute()
    {

        if ($this->session->isLoggedIn()) {
            $customerId = $this->session->getCustomer()->getId();

            $page_object = $this->pageFactory->create();
            $page_object->getConfig()->getTitle()->set(__('My Saved Credit Cards'));
            $page_object->getLayout()->getBlock('shift4_storedcard_index')->setCustomerId($customerId);
            return $page_object;
        } else {
            $this->_redirect('customer/account/login');
        }
    }
}
