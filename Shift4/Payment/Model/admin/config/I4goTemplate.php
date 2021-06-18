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
			['value' => 'side', 'label' => __('Side')],
			['value' => 'top', 'label' => __('Top')],
			['value' => 'choose', 'label' => __('Choose')]
		];
    }
}
