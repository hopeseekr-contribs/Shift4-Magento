<?php

namespace Shift4\Payment\Model\admin\config;

class LoggingOption
{

    public function toOptionArray()
    {
        return [
            ['value' => 'off', 'label' => __('off')],
            ['value' => 'problems', 'label' => __('Log Problems Only')],
            ['value' => 'all', 'label' => __('Log All Communications')]
        ];
    }
}
