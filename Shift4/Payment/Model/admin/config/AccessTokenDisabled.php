<?php

/*
 * Set the Access Token hidden and disabled
 *
 * @category    Shift4
 * @package     Payment
 * @author	Chetu Team
 */

namespace Shift4\Payment\Model\admin\config;

class AccessTokenDisabled extends \Magento\Config\Block\System\Config\Form\Field
{

    /**
     * Method for Set Element
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return String
     */
    protected function _getElementHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        $element->setReadonly('readonly');

        return parent::_getElementHtml($element);
    }
}
