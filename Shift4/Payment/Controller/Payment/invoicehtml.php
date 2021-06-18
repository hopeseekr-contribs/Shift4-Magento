<?php

/**
 * Shift4 Payment controller for invoice html
 *
 * @category    Shift4
 * @package     Payment
 * @author    Giedrius
 */

namespace Shift4\Payment\Controller\Payment;

use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class invoicehtml extends \Magento\Framework\App\Action\Action
{

    /**
     * @var OrderViewAuthorizationInterface
     */
    protected $orderAuthorization;

    /**
     * @var \Magento\Framework\Registry
     */
    protected $_coreRegistry;

    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * @param Context $context
     * @param OrderViewAuthorizationInterface $orderAuthorization
     * @param \Magento\Framework\Registry $registry
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        Context $context,
        \Magento\Framework\Registry $registry,
        PageFactory $resultPageFactory,
        \Magento\Sales\Api\InvoiceRepositoryInterface $invoiceRepositoryInterface
    ) {
        $this->_coreRegistry = $registry;
        $this->resultPageFactory = $resultPageFactory;
        parent::__construct($context);
        $this->invoiceRepositoryInterface = $invoiceRepositoryInterface;
    }

    /**
     * Print Invoice Action
     *
     * @return \Magento\Framework\Controller\Result\Redirect|\Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        $invoiceId = isset($_GET['invoice_id']) ? (int) $_GET['invoice_id'] : false;
        
        if ($invoiceId) {
            $invoice = $this->invoiceRepositoryInterface->get($invoiceId);
            $order = $invoice->getOrder();
        } else {
            die('no invoice id');
        }


            $this->_coreRegistry->register('current_order', $order);
        if (isset($invoice)) {
            $this->_coreRegistry->register('current_invoice', $invoice);
        }
            /** @var \Magento\Framework\View\Result\Page $resultPage */
            $resultPage = $this->resultPageFactory->create()->addHandle('print');
            return $resultPage;
    }
}
