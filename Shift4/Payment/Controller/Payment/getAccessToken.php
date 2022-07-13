<?php

namespace Shift4\Payment\Controller\Payment;

class getAccessToken extends \Magento\Framework\App\Action\Action
{

    /**
     * @var \Magento\Framework\App\Request\Http
     */
    protected $request;


    protected $mask = 'XXXXX-XXXX-XXXX-XXXX-XXXXX';

    /**
     * @var \Magento\Payment\Helper\Data
     */
    protected $paymentHelper;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\App\Request\Http $request,
        \Magento\Framework\App\Config\Storage\WriterInterface $configWriter
    ) {
        $this->request = $request;
        $this->configWriter = $configWriter;
        parent::__construct(
            $context
        );
    }

    /**
     * get the access token for the Merchant
     *
     * @param String authToken
     * @param String endPoint
     *
     * @return string
     */
    public function execute()
    {
        $authToken = $this->getRequest()->getParam('authToken');
        $endPoint = $this->getRequest()->getParam('endPoint');

        $pattern = '/^[a-fA-F0-9{8}\-a-fA-F0-9{4}\-a-fA-F0-9{4}\-a-fA-F0-9{16}]{35}$/';
        $isMatch = preg_match($pattern, $authToken);

        $data = [];
        $data['error_message'] = '';
        if (!$isMatch) {
            $data['error_message'] = __('The Auth Token you entered is not valid. Please try again.');
        } else {
            try {
                // Get the Access token for the Merchant
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                $result = $objectManager->create('Shift4\Payment\Model\Api')
                    ->getShift4AccessToken($authToken, $endPoint);

                if ($result['http_code'] == '200') {

                    $response = json_decode($result['data']);

                    $access_token = $response->result[0]->credential->accessToken;

                    if ($access_token) {

                        $this->configWriter->save('payment/shift4/live_access_token', $access_token, 'default');

                        $data['error_message'] = '';
                        $data['accessToken'] =  $this->maskToken($access_token);
                    } else {
                        $data['error_message'] = __('Error generating access token');
                        $data['accessToken'] = '';
                    }
                } else {
                    $data['error_message'] = $response->result[0]->error->longText;
                    $data['accessToken'] = '';
                }
            } catch (Exception $ex) {
                $data['error_message'] = $ex->getMessage();
                $data['accessToken'] = '';
            }
        }

        return $this->getResponse()->setBody(
            $this->_objectManager->get('Magento\Framework\Json\Helper\Data')->jsonEncode($data)
        );
    }

    protected function maskToken($token)
    {
        if ($token != '') {
            $masked = substr($token, 0, 3) . $this->mask . substr($token, -3, 3);
        } else {
            $masked = '';
        }
        return $masked;
    }
}
