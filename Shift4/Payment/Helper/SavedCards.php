<?php
namespace Shift4\Payment\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface as ScopeConfig;

class SavedCards
{
    
    protected $scopeConfig;
    protected $savedCardsDB;
    
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Shift4\Payment\Model\SavedCards $savedCardsDB
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->savedCardsDB = $savedCardsDB;
    }

    public function getSavedCardsHTML($customerId)
    {
        
        $savedCardsData = [];
        $scopeConfigGetValue = $this->scopeConfig->getValue('payment/shift4/enable_saved_cards', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        
        if ($scopeConfigGetValue) {
            
            $default = 'new';
            $defaultType = '';
            
            $savedCardsData['html'] = '';
            $savedCardsData['default'] = 'new';
            $savedCardsData['defaultType'] = '';

            $savedCards = $this->savedCardsDB->getCardsByCustomerId($customerId);
            
            if (!empty($savedCards)) { //generate saved cards
                $savedCardsOptions = [];
                $savedCardsHTML = '';
                
                foreach ($savedCards as $card) {
                    if ($card['cc_type'] == 'YC') {
                        $key = 1;
                    } else {
                        $key = 0;
                    }
                    
                    $savedCardsOptions[$key][] = $card;
                }

                $names = [
                    0 => __('My saved credit and debit cards'),
                    1 => __('My saved gift cards')
                ];
                
                foreach ($savedCardsOptions as $k => $cards) {
                    $savedCardsHTML .= '<optgroup label="'.$names[$k].'">';
                    foreach ($cards as $card) {
                        $savedCardsHTML .= '<option value="'.$card['token'].'" data-type="'.$card['cc_type'].'"';
                        if ($card['is_default'] == 1) {
                            $default = $card['token'];
                            $defaultType = $card['cc_type'];
                            $savedCardsHTML .= ' selected="selected"';
                        }
                        $savedCardsHTML .= '>'.$card['cc_type'].' (XXXX-'.$card['last_four'].')</option>';
                            
                    }
                    $savedCardsHTML .= '</optgroup>';
                }
                
				
                $savedCardsNew = '';
                $scopeConfigGetValue = $this->scopeConfig->getValue('payment/shift4/i4go_template', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
                if ($scopeConfigGetValue == 'choose') { 
					$savedCardsNew .= '<optgroup label="Wallets"><option value="wallets">Google pay or Apple pay</option></optgroup>';
				}
                $savedCardsNew .= '<optgroup label="'. __('New card') .'"><option value="new"';
                if ($default == 'new') {
                    $savedCardsNew .= ' selected="selected"';
                }
                $savedCardsNew .= '>'. __('New card') .'</option></optgroup>';
                $savedCardsHTML = $savedCardsNew.$savedCardsHTML;
                
                $savedCardsData['html'] = $savedCardsHTML;
                $savedCardsData['default'] = $default;
                $savedCardsData['defaultType'] = $defaultType;
                
            }
        }
        return $savedCardsData;
    }
}
