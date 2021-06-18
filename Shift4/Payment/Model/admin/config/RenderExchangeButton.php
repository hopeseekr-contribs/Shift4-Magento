<?php

/**
 * Render Token exchange button in the system config section
 *
 * @category    Shift4
 * @package     Payment
 * @author    Chetu Team
 */

namespace Shift4\Payment\Model\admin\config;

class RenderExchangeButton extends \Magento\Config\Block\System\Config\Form\Field
{

    /**
     * Method for Set Element
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return String
     */
    protected function _getElementHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        try {
            $this->setElement($element);

            $html = $element->getElementHtml();
            $html .= $this->getAfterElementHtml();

            return $html;
        } catch (\Exception $e) {
            $this->_logger->critical($e->getMessage());
        }
    }

    /**
     * Token exchange function and JavaScript for token exchange
     *
     * @param NULL
     *
     * @return string
     */
    public function getAfterElementHtml()
    {
        $url = $this->getUrl('payment/config/getAccessToken', []);
        $buttonScript = "<script>
		var exchangeAjaxUrl = '".$url."';

            function requestAccessToken(url) {
                var authToken = $('payment_us_shift4_section_shift4_auth_token').getValue();
                var endPoint = $('payment_us_shift4_section_shift4_server_addresses').getValue();

                var errorMsg = ''; 
                if (authToken == '') {
                    errorMsg += 'Auth token '; 
                    if (endPoint == '') {
                        errorMsg += 'and Server Address '; 
                    }
                } 

                if (errorMsg != '') {
                      alert('Please enter the ' + errorMsg + 'value for exchange request');
                      return;
                }
                
                new Ajax.Request(url, {
                    method:'post', 
                    parameters: { 
                        authToken: authToken,
                        endPoint: endPoint
                    }, 
                    requestHeaders: {Accept: 'application/json'},
                    onSuccess: function(response) {
                        var json = response.responseText.evalJSON();
                        $$(\"label[for='payment_us_shift4_section_shift4_auth_token']\").first().update('Auth Token*');
                        if (json.error_message != '') {
                            $('payment_us_shift4_section_shift4_auth_token').addClassName('required-entry');
                            alert(json.error_message);
                        } else {
							alert('Successfully exchange auth token.');
                            $$(\"label[for='payment_us_shift4_section_shift4_auth_token']\").first().update('Auth Token');
                            $('payment_us_shift4_section_shift4_auth_token').setValue('');
                            $('payment_us_shift4_section_shift4_access_token').setValue(json.accessToken);
                            $('payment_us_shift4_section_shift4_masked_access_token').setValue(json.accessToken);
                            $('row_payment_us_shift4_section_shift4_auth_token').hide();
                            $('row_payment_us_shift4_section_shift4_masked_access_token').show();
                            $('payment_us_shift4_section_shift4_auth_token').removeClassName('required-entry');
                            
                            jQuery('button.cancel').show();
                            jQuery('#payment_us_shift4_section_shift4_masked_access_token').css({'border':'1px solid #00C61D','box-shadow':'0 0 0 0 #fff inset','transition':'all 2s ease'});
                            setTimeout(function () {
                                jQuery('#payment_us_shift4_section_shift4_masked_access_token').css({'border':'1px solid #aaa','box-shadow':'0 0 0 0 #fff inset','transition':'all 2s ease'});
                            }, 1000);
                            unMaskAccessCode();
                        }
                    },
                    onFailure: function() {
                        $$(\"label[for='payment_us_shift4_section_shift4_auth_token']\").first().update('Auth Token*');
                        $('#payment_us_shift4_section_shift4_auth_token').attr('value', '');
                        $('payment_us_shift4_section_shift4_auth_token').addClassName('required-entry');
                        alert('An error occurred during token exchange. Please try again');
                    }
                });							
            }
            function cancelExchange(){
                $$(\"label[for='payment_us_shift4_section_shift4_auth_token']\").first().update('Auth Token');
                $('payment_us_shift4_section_shift4_auth_token').removeClassName('required-entry');
                $('row_payment_us_shift4_section_shift4_auth_token').hide();
                $('row_payment_us_shift4_section_shift4_masked_access_token').show();
            }


            /* Unmask access token code */
            function unMaskAccessCode() {
                var mask_value = $('payment_us_shift4_section_shift4_access_token').getValue();

                if (mask_value !== '' && typeof(mask_value) !== 'undefined') {
                    var mask_first = mask_value.substring(0, 4);
                    var mask_last = mask_value.substring(mask_value.length - 4, mask_value.length);
                    var unmask_value = mask_first + '-XXXX-XXXX-XXXX-XXXX-' + mask_last;
                    $('payment_us_shift4_section_shift4_masked_access_token').setValue(unmask_value);
                    $('row_payment_us_shift4_section_shift4_auth_token').hide();
                    $('row_payment_us_shift4_section_shift4_masked_access_token').show();
                } else {
                    $('row_payment_us_shift4_section_shift4_auth_token').show();
                    $('row_payment_us_shift4_section_shift4_masked_access_token').hide();
                }

                
                $('row_payment_us_shift4_section_shift4_access_token').hide();
            }
            /* END Unmask access token code */ 	
        </script> ";

        $html = parent::getAfterElementHtml();
        $url = $this->getBaseUrl() . 'shift4/payment/getAccessToken/';

        $html .= $this->getLayout()->createBlock('Magento\Backend\Block\Widget\Button')
                ->setType('button')
                ->setClass('scalable save')
                ->setLabel('Exchange')
                ->setId('shift4_exchange_tokens')
                ->toHtml();

        $html .= $this->getLayout()->createBlock('Magento\Backend\Block\Widget\Button')
                ->setType('button')
                ->setClass('scalable save cancel')
                ->setLabel('Cancel')
                ->setId('shift4_cancel_token_exchange')
                ->toHtml();

        return $html . $buttonScript;
    }
}
