<?php

namespace MalibuCommerce\MConnect\Helper;

use \Magento\Framework\App\Filesystem\DirectoryList;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    const ALLOWED_LOG_SIZE_TO_BE_VIEWED = 10485760; // in bytes, 10 MB
    const QUEUE_ITEM_MAX_MESSAGE_SIZE   = 16777200; // in bytes, ~16 MB

    /**
     * @var \MalibuCommerce\MConnect\Model\Config
     */
    protected $mConnectConfig;

    /**
     * @var \MalibuCommerce\MConnect\Helper\Mail
     */
    protected $mConnectMailer;

    /**
     * @var \MalibuCommerce\MConnect\Model\Queue
     */
    protected $queue;

    /**
     * @var \Magento\Framework\Registry
     */
    protected $registry;

    /**
     * Serializer interface instance.
     *
     * @var \Magento\Framework\Serialize\Serializer\Json
     */
    protected $serializer;

    public function __construct(
        \MalibuCommerce\MConnect\Model\Config $mConnectConfig,
        \MalibuCommerce\MConnect\Model\Queue $queue,
        \MalibuCommerce\MConnect\Helper\Mail $mConnectMailer,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\Serialize\Serializer\Json $serializer = null
    ) {
        $this->mConnectMailer = $mConnectMailer;
        $this->mConnectConfig = $mConnectConfig;
        $this->queue          = $queue;
        $this->registry = $registry;
        $this->serializer = $serializer ?: \Magento\Framework\App\ObjectManager::getInstance()
            ->get(\Magento\Framework\Serialize\Serializer\Json::class);
        parent::__construct($context);
    }

    /**
     * @param int  $id
     * @param bool $absolute
     * @param bool $nameOnly
     *
     * @return bool|string
     */
    public function getLog($id, $absolute = true, $nameOnly = false)
    {
        if ($this->isLogFileToDb()) {
            $data =  $this->getLogFromDatabase($id);
            if (!empty($data)) {
                return $data;
            } else {
                return $this->getLogFile($id, $absolute, $nameOnly);
            }
        }

        $file = $this->getLogFile($id, $absolute, $nameOnly);
        if (!file_exists($file)) {
            return $this->getLogFromDatabase($id);
        }
        return $file;
    }

    public function getLogFromDatabase($id)
    {
        $queueItem = $this->queue->load($id);
        if ($queueItem && !empty($queueItem->getLogs())) {
            return $queueItem->getLogs();
        }
        return;
    }

    /**
     * @param int  $id
     * @param bool $absolute
     * @param bool $nameOnly
     *
     * @return bool|string
     */
    public function getLogFile($id, $absolute = true, $nameOnly = false)
    {
        $directoryList = new DirectoryList(BP);

        $dir = 'mconnect';
        if ($id) {
            $file = 'queue_' . $id . '.log';
        } else {
            $file = 'navision_soap.log';
        }
        $logDirObj = $directoryList;
        $logDir = $logDirObj->getPath('log');
        $logDir .= DIRECTORY_SEPARATOR . $dir;
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0770, true);
        }

        $file = ($absolute ? $logDir : $dir) . DIRECTORY_SEPARATOR . $file;

        return !file_exists($file) && !$nameOnly ? false : $file;
    }

    public function getLogSize($file, $humanReadable = true)
    {
        if (is_string($file)) {
            $bytes = mb_strlen($file);
            if (!$humanReadable) {
                return $bytes;
            }

            return $this->getFormatedSize($bytes);
        }

        return $this->getFileSize($file, $humanReadable);
    }

    public function getFileSize($file, $humanReadable = true)
    {
        if (!file_exists($file)) {
            return false;
        }
        $bytes = filesize($file);
        if (!$humanReadable) {
            return $bytes;
        }

        return $this->getFormatedSize($bytes);
    }

    public function getFormatedSize($bytes)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return number_format($bytes, 2) . ' ' . $units[$pow];
    }

    public function getLogFileContents($queueItemId, $asString = true)
    {
        if ($dataLog = $this->getLog($queueItemId, true, true)) {
            $results = [];
            if (file_exists($dataLog)) {
                $contents = file_get_contents($dataLog);
                if (preg_match_all('~({.+})~', $contents, $matches)) {
                    foreach ($matches[1] as $match) {
                        $debug = json_decode($match);
                        $result = [];
                        foreach ($debug as $title => $data) {
                            if (preg_match('~({.+})~', $data, $matches2)) {
                                $data = json_decode($matches2[1]);
                            }
                            $result[$title] = $data;
                        }
                        $results[] = $result;
                    }
                }
            } else {
                $contents = $dataLog;
                $results[] = $this->serializer->unserialize($dataLog);
            }

            if (count($results)) {
                return $asString ? print_r($results, true) : $results;
            }

            return $contents;
        }

        return false;
    }

    /**
     * Render queue item status for HTML version
     *
     * @param string $status
     * @param string $message
     *
     * @return string
     */
    public function getQueueItemStatusHtml($status, $message)
    {
        $result = '';
        $style = 'text-transform: uppercase;'
                 . ' font-weight: bold;'
                 . ' color: white;'
                 . ' font-size: 10px;'
                 . ' width: 100%;'
                 . ' display: block;'
                 . ' text-align: center;'
                 . ' padding: 3px;'
                 . ' border-radius: 10px;';
        $title = htmlentities($message);
        $background = false;
        switch ($status) {
            case \MalibuCommerce\MConnect\Model\Queue::STATUS_PENDING:
                $background = '#9a9a9a';
                break;
            case \MalibuCommerce\MConnect\Model\Queue::STATUS_RUNNING:
                $background = '#28dade';
                break;
            case \MalibuCommerce\MConnect\Model\Queue::STATUS_SUCCESS:
                $background = '#00c500';
                break;
            case \MalibuCommerce\MConnect\Model\Queue::STATUS_ERROR:
                $background = '#ff0000';
                break;
            default:
                $result = $status;
        }
        if ($background) {
            $result = '<span title="' . $title . '" style="' . $style . ' background: ' . $background . ';">' . $status . '</span>';
        }

        return $result;
    }

    /**
     * @param            $request
     * @param            $location
     * @param            $action
     * @param \Throwable $e
     *
     * @return bool
     */
    public function logRequestError($request, $location, $action, \Throwable $e)
    {
        if (!$this->mConnectConfig->get('nav_connection/log')) {
            return false;
        }

        $this->logRequest(
            $request,
            $location,
            $action,
            500,
            null,
            'Error: ' . $e->getMessage() . "\n\n" . $e->getTraceAsString()
        );

        $request = $this->prepareLogRequest($request, $location, $action);
        $this->mConnectMailer->sendErrorEmail('An error occurred when connecting to Navision.', $request,
            $e->getMessage());

        return true;
    }

    /**
     * @param string|array $request
     * @param string       $location
     * @param string       $action
     * @param string|int   $code
     * @param string       $header
     * @param string       $body
     *
     * @return bool
     */
    public function logRequest($request, $location, $action, $code, $header, $body)
    {
        if (!$this->mConnectConfig->get('nav_connection/log')) {
            return false;
        }

        $queueItemId = $this->registry->registry('MALIBUCOMMERCE_MCONNET_ACTIVE_QUEUE_ITEM_ID');
        $request = $this->prepareLogRequest($request, $location, $action);
        $response = [
            'Code'          => $code,
            'Headers'       => $header,
            'Response Data' => $this->decodeRequest('/<responseXML>(.*)<\/responseXML>/', $body)
        ];
        $logData = array(
            'Request'  => $request,
            'Response' => $response
        );
        if ($this->isLogFileToDb()) {
            $queueItem = $this->queue->load($queueItemId);
            $queueItem->setLogs($this->serializer->serialize($logData))->save();
        } else {
            $logFile = $this->getLogFile($queueItemId, true, true);
            $writer = new \Zend\Log\Writer\Stream($logFile);
            $logger = new \Zend\Log\Logger();
            $logger->addWriter($writer);
            $logger->debug('Debug Data', $logData);
        }

        return true;
    }

    /**
     * @param string|array $request
     * @param string       $location
     * @param string       $action
     *
     * @return array
     */
    public function prepareLogRequest($request, $location, $action)
    {
        return [
            'Time'         => date('r'),
            'Location'     => $location,
            'PID'          => getmypid(),
            'Action'       => $action,
            'Request Data' => $this->decodeRequest('/<ns1:requestXML>(.*)<\/ns1:requestXML>/', $request),
        ];
    }

    /**
     * @param string $pattern
     * @param string $value
     *
     * @return bool|string
     */
    public function decodeRequest($pattern, $value)
    {
        if (is_string($value) && preg_match($pattern, $value, $matches)
            && isset($matches[1]) && preg_match('%^[a-zA-Z0-9/+]*={0,2}$%', $matches[1])
        ) {
            return base64_decode($matches[1]);
        }

        return $value;
    }

    /**
     * @return boolean
     */
    public function isLogFileToDb()
    {
        return (boolean)$this->mConnectConfig->get('nav_connection/log_to_db');
    }
}