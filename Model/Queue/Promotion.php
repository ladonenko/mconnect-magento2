<?php

namespace MalibuCommerce\MConnect\Model\Queue;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Serialize\Serializer\Json;

class Promotion extends \MalibuCommerce\MConnect\Model\Queue implements ImportableEntity
{
    const CODE                            = 'promotion';
    const CACHE_ID                        = 'mconnect_promotion_price';
    const CACHE_TAG                       = 'mconnect_promotion';
    const REGISTRY_KEY_NAV_PROMO_PRODUCTS = 'mconnect_promotion';
    const NAV_XML_NODE_ITEM_NAME          = 'items';
    const NAV_PAGE_NUMBER                 = 0;

    /**
     * @var \Magento\Framework\Registry
     */

    protected $registry;

    /**
     * @var \MalibuCommerce\MConnect\Model\Navision\Promotion
     */
    protected $navPromotion;

    /**
     * @var \MalibuCommerce\MConnect\Model\Config
     */
    protected $config;

    /**
     * Date
     *
     * @var \Magento\Framework\Stdlib\DateTime\DateTime
     */
    protected $date;

    /**
     * @var \MalibuCommerce\MConnect\Model\Queue\FlagFactory
     */
    protected $queueFlagFactory;

    /**
     * @var \Magento\Framework\App\CacheInterface
     */
    protected $cache;

    /**
     * @var \Magento\Framework\DataObjectFactory
     */
    protected $dataObjectFactory;

    protected $serializer;

    public function __construct(
        \Magento\Framework\Registry $registry,
        \MalibuCommerce\MConnect\Model\Navision\Promotion $navPromotion,
        \MalibuCommerce\MConnect\Model\Config $config,
        \Magento\Framework\Stdlib\DateTime\DateTime $date,
        \MalibuCommerce\MConnect\Model\Queue\FlagFactory $queueFlagFactory,
        \Magento\Framework\App\CacheInterface $cache,
        \Magento\Framework\DataObjectFactory $dataObjectFactory,
        Json $serializer = null
    ) {
        $this->registry = $registry;
        $this->navPromotion = $navPromotion;
        $this->config = $config;
        $this->date = $date;
        $this->queueFlagFactory = $queueFlagFactory;
        $this->cache = $cache;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->serializer = $serializer ? : ObjectManager::getInstance()->get(Json::class);
    }

    public function importAction($websiteId, $navPageNumber = 0)
    {
        return $this->processMagentoImport($this->navPromotion, $this, $websiteId, $navPageNumber);
    }

    /**
     * @param \Magento\Catalog\Model\Product|string $product
     * @param int                                   $qty
     * @param int                                   $websiteId
     *
     * @return bool
     */
    public function getPromoPrice($product, $qty = 1, $websiteId = 0)
    {
        if (!(bool)$this->config->getWebsiteData(self::CODE . '/import_enabled', $websiteId)) {

            return false;
        }

        if(is_string($product)) {
            $sku = $product;
        } else {
            $sku = $product->getSku();
        }

        $promoPrice = $this->getPriceFromCache($sku, $qty);
        if ($promoPrice == 'NULL') {
            return false;
        }
        if (!empty($promoPrice)) {
            return $promoPrice;
        }

        $prepareProducts = $this->registry->registry(self::REGISTRY_KEY_NAV_PROMO_PRODUCTS);
        $prepareProducts[$sku] = $qty;
        $this->registry->unregister(self::REGISTRY_KEY_NAV_PROMO_PRODUCTS);
        $this->registry->register(self::REGISTRY_KEY_NAV_PROMO_PRODUCTS, $prepareProducts);
        $navPageNumber = 0;
        $this->processMagentoImport($this->navPromotion, $this, $websiteId, $navPageNumber);

        return $this->getPriceFromCache($sku, $qty);
    }

    public function importEntity(\SimpleXMLElement $data, $websiteId)
    {
        foreach ($data->item as $item) {
            if (isset($item->price)) {
                $productPromoInfo = ['price' => (float)$item->price, 'quantity' => (int)$item->quantity];
                $this->savePromoPriceToCache($productPromoInfo, (string)$item->sku, $websiteId);
            }
        }

        return true;
    }

    public function getCacheId($sku, $qty)
    {
        return self::CACHE_ID . $sku . '_' . $qty;
    }

    public function getPriceFromCache($sku, $qty = 1)
    {
        $cache = $this->cache->load($this->getCacheId($sku, $qty));
        if (($qty > 1) && ($cache == false)) {
            $cache = $this->cache->load($this->getCacheId($sku, 1));
        }
        if ($cache != false) {
            $productPromoInfo = $this->serializer->unserialize($cache);
            if (isset($productPromoInfo['quantity']) && isset($productPromoInfo['price'])) {
                if ($qty >= $productPromoInfo['quantity']) {
                    return $productPromoInfo['price'];
                }
            }
        }

        return false;
    }

    public function savePromoPriceToCache($productPromoInfo, $sku, $websiteId = 0)
    {
        $lifeTime = $this->config->getWebsiteData(self::CODE . '/price_ttl', $websiteId);
        $this->cache->save(
            $this->serializer->serialize($productPromoInfo),
            $this->getCacheId($sku, $productPromoInfo['quantity']),
            [self::CACHE_TAG], $lifeTime
        );
    }

    public function runMultiplePromoPriceImport($website = 0)
    {
        $this->processMagentoImport($this->navPromotion, $this, $website, self::NAV_PAGE_NUMBER);
    }
}