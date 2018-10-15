<?php
namespace MalibuCommerce\MConnect\Observer;

class ProcessFrontFinalPriceObserver implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * @var \MalibuCommerce\MConnect\Model\Pricerule
     */
    protected $rule;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \MalibuCommerce\MConnect\Model\Pricerule $rule
    ) {
        $this->logger = $logger;
        $this->rule = $rule;
    }

    /**
     * Apply MConnect product price rule to Product's final price
     *
     * @param \Magento\Framework\Event\Observer $observer
     *
     * @return $this
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $finalPrice = null;
        try {
            /** @var \Magento\Catalog\Model\Product $product */
            $product = $observer->getProduct();
            $mconnectPrice = $this->rule->matchDiscountPrice($product, $observer->getQty());

            if ($mconnectPrice === false) {

                return $this;
            }

            if (!$product->hasData('final_price') || $mconnectPrice <= $product->getData('final_price')) {
                $finalPrice = $mconnectPrice;
            }
        } catch (\Throwable $e) {
            $this->logger->critical($e);
        }

        if ($finalPrice === null) {

            return $this;
        } else {
            $product->setPrice($finalPrice);
            $product->setFinalPrice($finalPrice);
        }

        return $this;
    }
}
