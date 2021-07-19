<?php
namespace Shift4\Payment\Controller\Adminhtml\Order\Create;

class Cancel extends \Magento\Sales\Controller\Adminhtml\Order\Create\Cancel
{
	protected $shift4;
	
	public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Catalog\Helper\Product $productHelper,
        \Magento\Framework\Escaper $escaper,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Backend\Model\View\Result\ForwardFactory $resultForwardFactory,
		\Shift4\Payment\Model\Shift4 $shift4
    ) {
		$this->shift4 = $shift4;
		parent::__construct(
            $context,
			$productHelper,
			$escaper,
			$resultPageFactory,
			$resultForwardFactory
        );
    }
	
	
    /**
     * Cancel order create
     *
     * @return \Magento\Backend\Model\View\Result\Redirect
     */
    public function execute()
    {
		$this->shift4->cancelAllPartialPayments();
		return parent::execute();
    }
}
