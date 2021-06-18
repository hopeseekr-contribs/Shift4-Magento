<?php

namespace Shift4\Payment\Model\admin\config;

class ProcessingMode
{

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [['value' => 'demo', 'label' => __('Demo')], ['value' => 'live', 'label' => __('Live')]];
    }
}
