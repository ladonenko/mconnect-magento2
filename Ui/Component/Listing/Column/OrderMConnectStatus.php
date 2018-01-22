<?php

namespace MalibuCommerce\MConnect\Ui\Component\Listing\Column;

use \Magento\Sales\Api\OrderRepositoryInterface;
use \MalibuCommerce\MConnect\Model\Queue;
use \Magento\Framework\View\Element\UiComponent\ContextInterface;
use \Magento\Framework\View\Element\UiComponentFactory;

class OrderMConnectStatus extends \Magento\Ui\Component\Listing\Columns\Column
{
    /**
     * @var OrderRepositoryInterface
     */
    protected $_orderRepository;

    /**
     * @var Queue
     */
    protected $_queue;

    /**
     * @var \MalibuCommerce\MConnect\Helper\Data
     */
    protected $helper;

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        OrderRepositoryInterface $orderRepository,
        Queue $queue,
        \MalibuCommerce\MConnect\Helper\Data $helper,
        array $components = [],
        array $data = [])
    {
        $this->_orderRepository = $orderRepository;
        $this->_queue = $queue;
        $this->helper = $helper;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                /** @var \MalibuCommerce\MConnect\Model\Resource\Queue\Collection $queueCollection */
                $queueCollection = $this->_queue->getCollection()
                    ->addFilter('code', 'order')
                    ->addFilter('entity_id', $item['entity_id'])
                    ->setOrder('finished_at', 'desc');

                /** @var \MalibuCommerce\MConnect\Model\Queue $queueItem */
                $queueItem = $queueCollection->getFirstItem();
                $status = $this->helper->getQueueItemStatusHtml($queueItem);
                $item[$this->getData('name')] = $status;
            }
        }

        return $dataSource;
    }
}