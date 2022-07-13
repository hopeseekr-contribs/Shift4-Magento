<?php
namespace Shift4\Payment\Logger;

use Monolog\Logger;

class DebugHandler extends \Magento\Framework\Logger\Handler\Base
{
    /**
     * Logging level
     * @var int
     */
    protected $loggerType = Logger::INFO;

    /**
     * File name
     * @var string
     */
    protected $fileName = '/var/log/shift4debug.log';
}
