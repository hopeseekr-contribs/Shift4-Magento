<?php

namespace Shift4\Payment\Controller\Adminhtml\Config;

class GetAccessToken extends \Magento\Backend\App\Action
{
    private $configWriter;
    protected $mask = 'XXXXX-XXXX-XXXX-XXXX-XXXXX';
    /**
     * Constructor
     *
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\App\Config\Storage\WriterInterface $configWriter,
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \Shift4\Payment\Model\Api $api
    ) {
        $this->configWriter = $configWriter;
        parent::__construct($context);
        $this->api = $api;
        $this->jsonHelper = $jsonHelper;
    }

    public function execute()
    {

        $authToken = $this->getRequest()->getParam('authToken');
        $endPoint = $this->getRequest()->getParam('endPoint');

        /*
            Access token format example: 7DBDD96D-F268-F7C0-C4FD2184CDCC824C
        */
        $pattern = '/^[a-fA-F0-9{8}\-a-fA-F0-9{4}\-a-fA-F0-9{4}\-a-fA-F0-9{16}]{35}$/';
        $isMatch = preg_match($pattern, $authToken);

        $data = [];
        $data['error_message'] = '';
        if (!$isMatch) {
            $data['error_message'] = __('The Auth Token you entered is not valid. Please try again.');
        } else {
            try {
                $result = $this->api->getShift4AccessToken($authToken, $endPoint);
                if (!empty($result['http_code']) && $result['http_code'] == '200') {

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
                    $data['error_message'] = $response->result[0]->error->longText
                        ? $response->result[0]->error->longText
                        : __('Error generating access token');
                    $data['accessToken'] = '';
                }
            } catch (Exception $ex) {
                $data['error_message'] = $ex->getMessage();
                $data['accessToken'] = '';
            }
        }

        return $this->getResponse()->setBody($this->jsonHelper->jsonEncode($data));
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
