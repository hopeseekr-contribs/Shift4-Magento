<?php

/**
 * Shift4 Payment controller for cancel all partial authorizations
 *
 * @category Shift4
 * @package  Payment
 * @author   Giedrius
 */

namespace Shift4\Payment\Controller\Payment;

class cancelPayment extends \Magento\Framework\App\Action\Action
{

    /**
     * @var \Magento\Framework\App\Request\Http
     */
    protected $request;
    protected $shift4;

    public function __construct(\Shift4\Payment\Model\Shift4 $shift4, \Magento\Framework\App\Request\Http $request, \Magento\Framework\App\Action\Context $context)
    {
        $this->shift4 = $shift4;
        $this->request = $request;
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
        $invoiceId = $this->getRequest()->getParam('shift4invoice');
        return $this->getResponse()->setBody($this->shift4->cancelPartialPayment($invoiceId));
    }
}
