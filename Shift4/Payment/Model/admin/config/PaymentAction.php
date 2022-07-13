<?php

namespace Shift4\Payment\Model\admin\config;

class PaymentAction
{

    /**
     * Retrieve action authorize capture code
     *
     * @return string
     * @throws \Magento\Payment\Model\Method\AbstractMethod
     */
    public function toOptionArray()
    {
        return [['value' => \Magento\Payment\Model\Method\AbstractMethod::ACTION_AUTHORIZE, 'label' => __('Book and Ship')], ['value' => \Magento\Payment\Model\Method\AbstractMethod::ACTION_AUTHORIZE_CAPTURE, 'label' => __('Immediate Charge')]];
    }
}
