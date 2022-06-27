<?php

/**
 * Shift4 Payment controller for updating guest email
 *
 * @category Shift4
 * @package  Payment
 * @author   Giedrius
 */

namespace Shift4\Payment\Controller\Payment;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\ResultInterface;
use Psr\Log\LoggerInterface;

class saveGuestData extends \Magento\Framework\App\Action\Action
{

    /**
     * @var Http
     */
    protected $request;

    /**
     * @var \Magento\Payment\Helper\Data
     */
    protected $paymentHelper;

    /**
     * @var Session
     */
    protected $checkoutSession;

    /**
     * @var LoggerInterface
     */
    protected $logger;
    protected $shift4;

    public function __construct(Context $context, Http $request, Session $checkoutSession, LoggerInterface $logger)
    {
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
     * @return \Magento\Framework\App\ResponseInterface|ResultInterface|void
     */
    public function execute()
    {
        $email = $this->getRequest()->getParam('email');

        $this->checkoutSession->setData('guestEmail', $email);

        $this->getResponse()->setBody($email);
    }
}
