<?php

namespace MalibuCommerce\MConnect\Observer;

class CatalogProductLoadAfterObserver implements \Magento\Framework\Event\ObserverInterface
{
    const CODE = 'tier_price';
    const SORT_ORDER_ASC = 'ASC';
    const MIN_QTY_TO_SHOW_TIER_PRICE = 1;
    const CUSTOMER_GROUP = 32000;

    /**
     * @var \MalibuCommerce\MConnect\Model\Pricerule
     */
    protected $rule;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \MalibuCommerce\MConnect\Model\Config
     */
    protected $config;

    /**
     * @var \MalibuCommerce\MConnect\Model\Queue\Promotion
     */
    protected $promotion;

    /**
     * Showtier price constructor.
     *
     * @param \Psr\Log\LoggerInterface                               $logger
     * @param \MalibuCommerce\MConnect\Model\Pricerule               $rule
     * @param \MalibuCommerce\MConnect\Model\Config                  $config
     */
    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \MalibuCommerce\MConnect\Model\Pricerule $rule,
        \MalibuCommerce\MConnect\Model\Config $config,
        \MalibuCommerce\MConnect\Model\Queue\Promotion $promotion
    ) {
        $this->logger = $logger;
        $this->rule = $rule;
        $this->config = $config;
        $this->promotion = $promotion;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $product = $observer->getEvent()->getProduct();
        $websiteId = $product->getStore()->getWebsiteId();
        if (!(bool)$this->config->getWebsiteData(self::CODE . '/is_enabled', $websiteId)) {

            return false;
        }

        /** @var \MalibuCommerce\MConnect\Model\Resource\Pricerule\Collection $collection */
        $collection = $this->rule->getResourceCollection();
        $collection
            ->applySkuFilter($product->getSku())
            ->applyWebsiteFilter($websiteId)
            ->applyCustomerFilter()
            ->applyFromToDateFilter()
            ->setOrder('price', self::SORT_ORDER_ASC)
            ->addFieldToFilter('qty_min', array(
                array('from' => self::MIN_QTY_TO_SHOW_TIER_PRICE),
            ));
        if ($collection->getSize() > 0) {
            foreach ($collection as $item) {
                $mconnectPromoPrice = $this->promotion->getPromoPrice((string)$item->getData('sku'), $item->getData('qty_min'), $websiteId);
                if ($mconnectPromoPrice === false) {
                    $finalPrice = $item->getData('price');
                } else {
                    $finalPrice = $mconnectPromoPrice;
                }
                $tierPrices[] = ['website_id' => $websiteId, 'cust_group' => self::CUSTOMER_GROUP, 'price_qty' => $item->getData('qty_min'), 'price' => $finalPrice];
            }
            $product->setTierPrice($tierPrices);
        }

        return $this;
    }
}