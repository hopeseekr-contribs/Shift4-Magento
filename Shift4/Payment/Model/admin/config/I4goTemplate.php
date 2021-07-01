<?php

namespace Shift4\Payment\Model\admin\config;

class I4goTemplate
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
			['value' => 'shift4shop', 'label' => __('Shift4 shop')],
			['value' => 'default', 'label' => __('Default')],
			['value' => 'bootstrap1', 'label' => __('Bootstrap1')]
		];
    }
}
