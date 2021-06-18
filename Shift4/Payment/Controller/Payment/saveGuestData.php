<?php

/**
 * Shift4 Payment controller for updating guest email
 *
 * @category    Shift4
 * @package     Payment
 * @author	Giedrius
 */

namespace Shift4\Payment\Controller\Payment;

class saveGuestData extends \Magento\Framework\App\Action\Action {

    /**
     * @var \Magento\Framework\App\Request\Http
     */
    protected $request;

    /**
     * @var \Magento\Payment\Helper\Data
     */
    protected $paymentHelper;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;
    protected $shift4;

    public function __construct(\Magento\Framework\App\Action\Context $context, \Magento\Framework\App\Request\Http $request, \Magento\Checkout\Model\Session $checkoutSession, \Psr\Log\LoggerInterface $logger) {
        $this->request = $request;
        $this->checkoutSession = $checkoutSession;
        $this->logger = $logger;
        parent::__construct(
                $context
        );
    }

    /**
     * cancel preauthorized amount.
     *
     * @param String unique_id
     * @param String invoice_id
     *
     * @return Json
     */
    public function execute() {
        $email = $this->getRequest()->getParam('email');
        
		$this->checkoutSession->setData('guestEmail', $email);	

        echo $email;
        exit;
    }

}
