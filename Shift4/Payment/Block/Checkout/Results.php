<?php

namespace Shift4\Payment\Block\Checkout;

class Results extends \Magento\Multishipping\Block\Checkout\Results
{
    /**
     * Gets the Payment method for the last order.
     *
     * @return string Method.
     */
	
    public function getPaymentMethod()
    {
		
		$paymentMethod = $this->getCheckout()->getQuote()->getPayment()->getMethodInstance()->getCode();
        return $paymentMethod;
    }
	
}
