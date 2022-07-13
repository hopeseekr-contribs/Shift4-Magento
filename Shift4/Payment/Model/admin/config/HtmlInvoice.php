<?php

namespace Shift4\Payment\Model\admin\config;

class HtmlInvoice
{

    /**
     * Retrieve action authorize capture code
     *
     * @return string
     * @throws \Magento\Payment\Model\Method\AbstractMethod
     */
    public function toOptionArray()
    {
        return [
            ['value' => 3, 'label' => __('Full invoice')],
            ['value' => 2, 'label' => __('Simple invoice')],
            ['value' => 1, 'label' => __('Magento order number only')],
            ['value' => 0, 'label' => __('No notes')]
        ];
    }
}
