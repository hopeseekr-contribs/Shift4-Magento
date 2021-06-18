<?php

/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Shift4\Payment\Block\Adminhtml\Order\View\Tab;

use Shift4\Payment\Helper\PaymentInfo as PaymentInfo;

/**
 * Order information tab
 *
 * @author      Magento Core Team <core@magentocommerce.com>
 */
class Info extends \Magento\Sales\Block\Adminhtml\Order\View\Tab\Info
{
    public function getPaymentHtml()
    {

        $order = $this->getOrder();
        $payment = $order->getPayment();
        $paymentInfo = new PaymentInfo();
        
        $returnHtml = $paymentInfo->generatePaymentInformationTable($payment);
        
        return $returnHtml;
    }
}
