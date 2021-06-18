<?php
namespace Shift4\Payment\Helper;

class PaymentInfo
{
    
    public function generatePaymentInformationTable($payment, $invoice = false)
    {

        $transactionsDB = \Magento\Framework\App\ObjectManager::getInstance()->get('Shift4\Payment\Model\TransactionLog');
        if ($invoice) {
            $cards = $transactionsDB->getTransactionsByInvoiceId($invoice->getIncrementId());
        } else {
            $cards = $transactionsDB->getTransactionsByOrderId($payment->getOrder()->getId());
        }

        $paymentMethod = $payment->getMethodInstance()->getTitle();
        $returnHtml = '<dl class="payment-method"><dt class="title">' . (isset($paymentMethod) ? nl2br($paymentMethod) : '') . '</dt>';

        $cardCount = 1;
        if (isset($cards) && !empty($cards)) {
            $allowedTypes = ['authorization', 'capture', 'sale', 'refund'];
            $returnHtml .= '<table class="data table data-table admin__table-secondary">';
            foreach ($cards as $card) {
                if (!in_array($card['transaction_mode'], $allowedTypes)) {
                    continue;
                }
                $authorizationCode = false;
                $amount = 0;
                $utgResponse = json_decode($card['utg_response']);
                if (json_last_error() == JSON_ERROR_NONE) {
                    $amount = (float) @$utgResponse->result[0]->amount->total;
                    $authorizationCode = (string) @$utgResponse->result[0]->transaction->authorizationCode;
                }

                $transactionType = (isset($card['transaction_mode']) ? $card['transaction_mode'] : '') ;
                $t = strtotime($card['transaction_date']);
                $date = date('d/m/Y H:i:s', $t);

                $returnHtml .= '<tr><th colspan="2"><strong> '. __('Transaction') .' #'
                    . $cardCount . '</strong>'.(@$date ? '&nbsp;&nbsp;'.$date :'').'</th></tr>
                    <tr>
                        <th scope="row"><strong>'. __('Card Type:') .'</strong></th>
                        <td><span class="s4card_type">' . (isset($card['card_type']) ? $card['card_type'] : '') .
                        '</span></td>
                    </tr>
                    <tr>
                        <th scope="row"><strong>'. __('Card Number:') .'</strong></th>
                        <td><span class="s4card_number">' . (isset($card['card_number']) ? $card['card_number'] : '') .
                    '</span></td>
                    </tr>
                    <tr>
                        <th scope="row"><strong>'. __('Transaction Type:') .'</strong></th>
                        <td>' . ($card['voided'] ? '<span style="color:#ff0000">
                            <span class="s4transaction_type">void</span>
                            </span>' : '<span class="s4transaction_type">'.$card['transaction_mode'].'</span>') . '</td>
                    </tr>
                    <tr>
                        <th scope="row"><strong>'. __('Processed Amount:') .'</strong></th>
                        <td>' . ((@$card['transaction_mode'] == 'refund') ? '<span style="color:#ff0000">
                            (<span class="s4refunded-amount">$'.$amount.'</span>)</span>' :
                            '<span class="s4processed-amount">$'.$amount.'</span>'). '</td>
                    </tr>
                    <tr>
                        <th scope="row"><strong>'. __('Shift4 Invoice ID:') .'</strong></th>
                        <td><span class="s4invoice">' . (isset($card['shift4_invoice']) ? $card['shift4_invoice'] : '') . '</span></td>
                    </tr>
                    '.($authorizationCode ? '<tr>
                        <th scope="row"><strong>Authorization Code</strong></th><td>' . $authorizationCode . '</td>
                        </tr>' : '').'
                    <tr><td colspan="2">&nbsp;</td></tr>';

                $cardCount++;
            }
            $returnHtml .= '</table>';
        }
        $returnHtml .= '</dl>';
        return $returnHtml;
    }
}
