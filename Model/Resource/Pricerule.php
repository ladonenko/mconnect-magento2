<?php
namespace MalibuCommerce\MConnect\Model\Resource;

class Pricerule extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    public function _construct()
    {
        $this->_init('malibucommerce_mconnect_price_rule', 'id');
    }
}
