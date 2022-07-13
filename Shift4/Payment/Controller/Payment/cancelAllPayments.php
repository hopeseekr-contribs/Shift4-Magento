<?php

/**
 * Shift4 Payment controller for cancel all partial authorizations
 *
 * @category    Shift4
 * @package     Payment
 * @author    Giedrius
 */

namespace Shift4\Payment\Controller\Payment;

class cancelAllPayments extends \Magento\Framework\App\Action\Action
{

    /**
     * @var \Magento\Framework\App\Request\Http
     */
    protected $shift4;

    public function __construct(\Shift4\Payment\Model\Shift4 $shift4, \Magento\Framework\App\Action\Context $context)
    {
        $this->shift4 = $shift4;
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
        return $this->getResponse()->setBody($this->shift4->cancelAllPartialPayments());
    }
}
