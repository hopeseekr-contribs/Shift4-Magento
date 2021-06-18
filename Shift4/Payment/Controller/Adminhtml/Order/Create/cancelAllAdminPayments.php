<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Shift4\Payment\Controller\Adminhtml\Order\Create;

class cancelAllAdminPayments extends \Magento\Backend\App\Action
{

    protected $shift4;
    
    public function __construct(\Shift4\Payment\Model\Shift4 $shift4, \Magento\Backend\App\Action\Context $context)
    {
        $this->shift4 = $shift4;
        parent::__construct(
            $context
        );
    }
    
    
    /**
     * Cancel order create
     *
     * @return
     */
    public function execute()
    {
        echo $this->shift4->cancelAllPartialPayments();
        die();
    }
}
