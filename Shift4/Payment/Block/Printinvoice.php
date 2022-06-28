<?php

namespace Shift4\Payment\Block;

class Printinvoice extends \Magento\Sales\Block\Items\AbstractItems
{

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $_locale;
    protected $_currencyFactory;
    protected $order;
    protected $invoice = false;
    protected $addressRenderer;
    protected $_addressConfig;
    protected $_storeInfo;
    protected $orderFactory;

    /**
     * Return the list of customer's stored cards
     *
     * @return Shift4_Payment_Model_Resource_Customerstored_Collection|null
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Sales\Model\Order\Address\Renderer $addressRenderer,
        \Magento\Customer\Model\Address\Config $addressConfig,
        \Magento\Store\Model\Information $storeInfo,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Store\Model\StoreManager $storeManager,
        \Magento\Directory\Model\CurrencyFactory $currencyFactory,
        \Shift4\Payment\Model\TransactionLog $transactionLog,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $data
        );

        $this->_storeManager = $storeManager;
        $this->_currencyFactory = $currencyFactory;
        $this->addressRenderer = $addressRenderer;

        $currencyCode = $this->_storeManager->getStore()->getCurrentCurrencyCode();
        $currency = $this->_currencyFactory->create()->load($currencyCode);
        $this->_symbol = $currency->getCurrencySymbol();
        $this->_addressConfig = $addressConfig;

        $this->_storeInfo = $storeInfo;
        $this->orderFactory = $orderFactory;
        $this->transactionLog = $transactionLog;

        $this->getLayout()
            ->createBlock(\Magento\Tax\Block\Item\Price\Renderer::class, 'item_unit_price')
            ->setTemplate('Shift4_Payment::unit.phtml');
        $this->getLayout()
            ->createBlock(\Magento\Tax\Block\Item\Price\Renderer::class, 'item_row_total')
            ->setTemplate('Shift4_Payment::row.phtml');
        $this->getLayout()
            ->createBlock(\Magento\Tax\Block\Item\Price\Renderer::class, 'item_row_total_after_discount')
            ->setTemplate('Shift4_Payment::total_after_discount.phtml');
    }

    public function getOrder()
    {
        return $this->order;
    }

    public function getInvoice()
    {
        return $this->invoice;
    }

    public function getStoreName()
    {
        return $this->_storeManager->getStore()->getName();
    }

    public function getStorePhone()
    {
        return $this->_storeInfo->getStoreInformationObject($this->_storeManager->getStore())->getPhone();
    }

    public function getStoreEmail()
    {
        return $this->_scopeConfig->getValue(
            'trans_email/ident_sales/email',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function getStoreUrl()
    {
        return $this->_storeManager->getStore()->getBaseUrl();
    }

    public function getOrderItems()
    {
        $orderItems = [];
        if ($this->invoice) {
            $items = $this->invoice->getAllItems();
            foreach ($items as $item) {
                if (!$item->getOrderItem()->getParentItem()) {
                    $orderItems[] = $item;
                }
            }
        } else {
            $items = $this->order->getAllItems();
            foreach ($items as $item) {
                $orderItems[] = $item;
            }
        }
        return $orderItems;
    }

    public function setInformation($order, $invoice = false)
    {
        //$order = $this->orderFactory->create()->load($order);

        $this->order = $order;
        if ($invoice) {
            $this->invoice = $invoice;
        }
        return $this;
    }

    public function getAddressHtml($type = 'billing')
    {
        if ($type == 'billing') {
            $address = $this->order->getBillingAddress();
        } else {
            $address = $this->order->getShippingAddress();
        }
        $renderer = $this->_addressConfig->getFormatByCode('html')->getRenderer();
        return $renderer->renderArray($address);
    }

    public function formatAddress(\Magento\Sales\Model\Order\Address $address, $format)
    {
        return $this->addressRenderer->format($address, $format);
    }

    public function getHTML()
    {
        $this->setTemplate('Shift4_Payment::invoice.phtml');
        return parent::_toHtml();
    }

    public function getTotalsHtml()
    {
        if ($this->invoice) {
            $totals = $this->getLayout()
                ->createBlock(\Magento\Sales\Block\Order\Invoice\Totals::class, 'invoice_totals')
                ->setTemplate('Shift4_Payment::totals.phtml');
        } else {
            $totals = $this->getLayout()
                ->createBlock(\Magento\Sales\Block\Order\Totals::class, 'invoice_totals')
                ->setTemplate('Shift4_Payment::totals.phtml');
        }

        $this->setChild('invoice_totals', $totals);

        if (class_exists('\Magento\CustomerBalance\Block\Sales\Order\Customerbalance')) {
			$customerbalance = $this->getLayout()
				->createBlock(\Magento\CustomerBalance\Block\Sales\Order\Customerbalance::class, 'customerbalance')
				->setTemplate('Shift4_Payment::customerbalance.phtml');
			$totals->setChild('customerbalance', $customerbalance);
		}

        $tax = $this->getLayout()
            ->createBlock(\Magento\Tax\Block\Sales\Order\Tax::class, 'tax')
            ->setTemplate('Shift4_Payment::tax.phtml');
        $totals->setChild('tax', $tax);

        $html = '';
        if ($totals) {
            $totals->setOrder($this->order);
            if ($this->invoice) {
                $totals->setInvoice($this->invoice);
            }

            $html = $totals->toHtml();
        }

        //echo $html; die();
        return $html;
    }

    public function itemRenderer($type, $item)
    {
        /** @var \Magento\Framework\View\Element\RendererList $rendererList */
        $item->setOrder($this->order);
        //$item->setInvoice($this->invoice);

        $typeBlock = $this->getLayout()
            ->createBlock(\Magento\Sales\Block\Order\Item\Renderer\DefaultRenderer::class)
            ->setItem($item)
            ->setTemplate('Shift4_Payment::renderer.phtml');

        $rendererList = $this->getLayout()
            ->createBlock(\Magento\Framework\View\Element\RendererList::class)
            ->setChild($type, $typeBlock);

        if (!$rendererList) {
            throw new \RuntimeException('Renderer list for block "' . $this->getNameInLayout() . '" is not defined');
        }
        $overriddenTemplates = $this->getOverriddenTemplates() ?: [];
        $template = isset($overriddenTemplates[$type]) ? $overriddenTemplates[$type] : $this->getRendererTemplate();
        $renderer = $rendererList->getRenderer($type, self::DEFAULT_TYPE, $template);
        $renderer->setRenderedBlock($this);
        return $renderer;
    }

    public function getItemHtml($item)
    {
        if (!$item->getParentItem()) {
            if ($item->getOrderItem()) {
                $type = $item->getOrderItem()->getProductType();
            } elseif ($item instanceof \Magento\Quote\Model\Quote\Address\Item) {
                $type = $item->getQuoteItem()->getProductType();
            } else {
                $type = $item->getProductType();
            }

            $block = $this->itemRenderer($type, $item);

            return $block->toHtml();
        }
    }

    public function getInvoiceIncrementId()
    {
        if ($this->invoice) {
            if ($this->invoice->getIncrementId()) {
                return $this->invoice->getIncrementId();
            } else {
                $incrementId = $this->transactionLog->getNextInvoiceId();
                return $incrementId;
            }
        } else {
            return false;
        }
    }

    private function getTemplateDir()
    {
        return getcwd() . '/app/code/Shift4/Payment/view/frontend/templates/';
    }
}
