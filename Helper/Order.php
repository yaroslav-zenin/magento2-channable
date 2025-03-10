<?php
/**
 * Copyright © 2019 Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magmodules\Channable\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magmodules\Channable\Helper\General as GeneralHelper;
use Magmodules\Channable\Service\Order\TestData;

/**
 * Class Order
 *
 * @package Magmodules\Channable\Helper
 */
class Order extends AbstractHelper
{

    const XPATH_ORDER_ENABLE = 'magmodules_channable_marketplace/general/enable';
    const XPATH_IMPORT_CUSTOMER = 'magmodules_channable_marketplace/order/import_customer';
    const XPATH_CUSTOMER_GROUP_ID = 'magmodules_channable_marketplace/order/customers_group';
    const XPATH_INVOICE_ORDER = 'magmodules_channable_marketplace/order/invoice_order';
    const XPATH_USE_CUSTOM_STATUS = 'magmodules_channable_marketplace/order/use_custom_status';
    const XPATH_CUSTOM_STATUS = 'magmodules_channable_marketplace/order/custom_status';
    const XPATH_SEPERATE_HOUSENUMBER = 'magmodules_channable_marketplace/order/seperate_housenumber';
    const XPATH_SHIPPING_METHOD = 'magmodules_channable_marketplace/order/shipping_method';
    const XPATH_SHIPPING_CUSTOM = 'magmodules_channable_marketplace/order/shipping_method_custom';
    const XPATH_SHIPPING_METHOD_FALLBACK = 'magmodules_channable_marketplace/order/shipping_method_fallback';
    const XPATH_USE_CHANNEL_ORDERID = 'magmodules_channable_marketplace/order/channel_orderid';
    const XPATH_ENABLE_BACKORDERS = 'magmodules_channable_marketplace/order/backorders';
    const XPATH_LVB_ENABLED = 'magmodules_channable_marketplace/order/lvb';
    const XPATH_LVB_SKIP_STOCK = 'magmodules_channable_marketplace/order/lvb_stock';
    const XPATH_LVB_AUTO_SHIP = 'magmodules_channable_marketplace/order/lvb_ship';
    const XPATH_ORDERID_PREFIX = 'magmodules_channable_marketplace/order/orderid_prefix';
    const XPATH_ORDERID_ALPHANUMERIC = 'magmodules_channable_marketplace/order/orderid_alphanumeric';
    const XPATH_LOG = 'magmodules_channable_marketplace/order/log';
    const XPATH_TAX_PRICE = 'tax/calculation/price_includes_tax';
    const XPATH_TAX_SHIPPING = 'tax/calculation/shipping_includes_tax';
    const XPATH_SHIPPING_TAX_CLASS = 'tax/classes/shipping_tax_class';
    const XPATH_CUSTOMER_STREET_LINES = 'customer/address/street_lines';

    /**
     * @var General
     */
    private $generalHelper;
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var TestData
     */
    private $testData;
    /**
     * @var OrderCollectionFactory
     */
    private $orderCollectionFactory;

    /**
     * Order constructor.
     *
     * @param Context                $context
     * @param StoreManagerInterface  $storeManager
     * @param General                $generalHelper
     * @param TestData               $testData
     * @param OrderCollectionFactory $orderCollectionFactory
     */
    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        GeneralHelper $generalHelper,
        TestData $testData,
        OrderCollectionFactory $orderCollectionFactory
    ) {
        $this->generalHelper = $generalHelper;
        $this->storeManager = $storeManager;
        $this->testData = $testData;
        $this->orderCollectionFactory = $orderCollectionFactory;
        parent::__construct($context);
    }

    /**
     * @param \Magento\Framework\App\RequestInterface $request
     *
     * @return bool|mixed
     */
    public function validateRequestData($request)
    {
        $storeId = $request->getParam('store');
        if (empty($storeId)) {
            return $this->jsonResponse('Store param missing in request');
        }

        $enabled = $this->generalHelper->getEnabled($storeId);
        if (empty($enabled)) {
            return $this->jsonResponse('Extension not enabled');
        }

        $order = $this->getEnabled($storeId);
        if (empty($order)) {
            return $this->jsonResponse('Order import not enabled');
        }

        $token = $this->generalHelper->getToken();
        if (empty($token)) {
            return $this->jsonResponse('Token not set in admin');
        }

        $code = trim(preg_replace('/\s+/', '', $request->getParam('code')));
        if (empty($code)) {
            return $this->jsonResponse('Token param missing in request');
        }

        if ($code != $token) {
            return $this->jsonResponse('Invalid token');
        }

        return false;
    }

    /**
     * @param string $errors
     * @param string $orderId
     *
     * @return array
     */
    public function jsonResponse($errors = '', $orderId = '')
    {
        $response = [];
        if (!empty($orderId)) {
            $response['validated'] = 'true';
            $response['order_id'] = $orderId;
        } else {
            $response['validated'] = 'false';
            $response['errors'] = $errors;
        }
        return $response;
    }

    /**
     * General check if Extension is enabled
     *
     * @param null $storeId
     *
     * @return mixed
     */
    public function getEnabled($storeId = null)
    {
        if (!$this->generalHelper->getEnabled($storeId)) {
            return false;
        }

        return $this->generalHelper->getStoreValue(self::XPATH_ORDER_ENABLE, $storeId);
    }

    /**
     * Validate if $data is json
     *
     * @param                                         $orderData
     * @param \Magento\Framework\App\RequestInterface $request
     *
     * @return bool|mixed
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function validateJsonData($orderData, $request)
    {
        $data = null;
        $test = $request->getParam('test');
        $lvb = $request->getParam('lvb');
        $country = $request->getParam('country', 'NL');
        $storeId = $request->getParam('store');

        if ($test) {
            $data = $this->testData->getOrder($test, $lvb, $country);
        } else {
            if ($orderData == null) {
                return $this->jsonResponse('Post data empty!');
            }
            $data = json_decode($orderData, true);
            if (json_last_error() != JSON_ERROR_NONE) {
                return $this->jsonResponse('Post not valid JSON-Data: ' . json_last_error_msg());
            }
        }

        if (empty($data)) {
            return $this->jsonResponse('No Order Data in post');
        }

        if (empty($data['channable_id'])) {
            return $this->jsonResponse('Post missing channable_id');
        }

        if (empty($data['channel_id'])) {
            return $this->jsonResponse('Post missing channel_id');
        }

        if (!empty($data['order_status'])) {
            if ($data['order_status'] == 'shipped') {
                if (!$this->getLvbEnabled($storeId)) {
                    return $this->jsonResponse('LVB Orders not enabled');
                }
            }
        }

        return $data;
    }

    /**
     * @param null $storeId
     *
     * @return mixed
     */
    public function getLvbEnabled($storeId = null)
    {
        return $this->generalHelper->getStoreValue(self::XPATH_LVB_ENABLED, $storeId);
    }

    /**
     * @param null $storeId
     *
     * @return mixed
     */
    public function getImportCustomer($storeId = null)
    {
        return $this->generalHelper->getStoreValue(self::XPATH_IMPORT_CUSTOMER, $storeId);
    }

    /**
     * @param null $storeId
     *
     * @return mixed
     */
    public function getInvoiceOrder($storeId = null)
    {
        return $this->generalHelper->getStoreValue(self::XPATH_INVOICE_ORDER, $storeId);
    }

    /**
     * @param null $storeId
     *
     * @return mixed
     */
    public function getProcessingStatus($storeId = null)
    {
        $useCustomStatus = $this->generalHelper->getStoreValue(self::XPATH_USE_CUSTOM_STATUS, $storeId);
        if (!$useCustomStatus) {
            return null;
        }

        return $this->generalHelper->getStoreValue(self::XPATH_CUSTOM_STATUS, $storeId);
    }

    /**
     * @param null $storeId
     *
     * @return mixed
     */
    public function getCustomerGroupId($storeId = null)
    {
        return $this->generalHelper->getStoreValue(self::XPATH_CUSTOMER_GROUP_ID, $storeId);
    }

    /**
     * @param null $storeId
     *
     * @return mixed
     */
    public function getUseChannelOrderId($storeId = null)
    {
        return $this->generalHelper->getStoreValue(self::XPATH_USE_CHANNEL_ORDERID, $storeId);
    }

    /**
     * @param null $storeId
     *
     * @return mixed
     */
    public function getEnableBackorders($storeId = null)
    {
        return $this->generalHelper->getStoreValue(self::XPATH_ENABLE_BACKORDERS, $storeId);
    }

    /**
     * @param null $storeId
     *
     * @return mixed
     */
    public function getLvbSkipStock($storeId = null)
    {
        return $this->generalHelper->getStoreValue(self::XPATH_LVB_SKIP_STOCK, $storeId);
    }

    /**
     * @param null $storeId
     *
     * @return mixed
     */
    public function getLvbAutoShip($storeId = null)
    {
        return $this->generalHelper->getStoreValue(self::XPATH_LVB_AUTO_SHIP, $storeId);
    }

    /**
     * @param null $storeId
     *
     * @return int
     */
    public function getSeperateHousenumber($storeId = null)
    {
        return $this->generalHelper->getStoreValue(self::XPATH_SEPERATE_HOUSENUMBER, $storeId);
    }

    /**
     * @param $storeId
     *
     * @return int
     */
    public function getCustomerStreetLines($storeId)
    {
        return (int) $this->generalHelper->getStoreValue(self::XPATH_CUSTOMER_STREET_LINES, $storeId);
    }

    /**
     * @param $channelId
     * @param $storeId
     *
     * @return mixed|null|string|string[]
     */
    public function getUniqueIncrementId($channelId, $storeId)
    {
        $prefix = $this->getOrderIdPrefix($storeId);
        if ($this->getUseAlphanumericOrderId($storeId)) {
            $newIncrementId = $prefix . preg_replace('/[^a-zA-Z0-9]+/', '', $channelId);
        } else {
            $newIncrementId = $prefix . preg_replace('/\s+/', '', $channelId);
        }

        $orderCheck = $this->orderCollectionFactory->create()
            ->addFieldToFilter('increment_id', ['eq' => $newIncrementId])
            ->getSize();

        if ($orderCheck) {
            /** @var \Magento\Sales\Model\Order $lastOrder */
            $lastOrder = $this->orderCollectionFactory->create()
                ->addFieldToFilter('increment_id', ['like' => $newIncrementId . '-%'])
                ->getLastItem();

            if ($lastOrder->getIncrementId()) {
                $lastIncrement = explode('-', $lastOrder->getIncrementId());
                $newIncrementId = substr($lastOrder->getIncrementId(), 0, -(strlen(end($lastIncrement)) + 1));
                $newIncrementId .= '-' . (end($lastIncrement) + 1);
            } else {
                $newIncrementId .= '-1';
            }
        }

        return $newIncrementId;
    }

    /**
     * @param null $storeId
     *
     * @return mixed
     */
    public function getOrderIdPrefix($storeId = null)
    {
        return $this->generalHelper->getStoreValue(self::XPATH_ORDERID_PREFIX, $storeId);
    }

    /**
     * @param null $storeId
     *
     * @return mixed
     */
    public function getUseAlphanumericOrderId($storeId = null)
    {
        return $this->generalHelper->getStoreValue(self::XPATH_ORDERID_ALPHANUMERIC, $storeId);
    }

    /**
     * @return array
     */
    public function getConfigData()
    {
        $configData = [];
        $stores = $this->storeManager->getStores();
        foreach ($stores as $store) {
            $storeId = $store->getStoreId();
            $configData[$storeId] = [
                'store_id'    => $storeId,
                'code'        => $store->getCode(),
                'name'        => $store->getName(),
                'is_active'   => $store->getIsActive(),
                'status'      => $this->generalHelper->getStoreValue(self::XPATH_ORDER_ENABLE, $storeId),
                'webhook_url' => $this->getWebhookUrl($storeId),
                'status_url'  => $this->getOrderStatusUrl($storeId)
            ];
        }
        return $configData;
    }

    /**
     * @param $storeId
     *
     * @return string
     */
    public function getWebhookUrl($storeId)
    {
        $url = $this->storeManager->getStore($storeId)->getBaseUrl();
        $token = $this->generalHelper->getToken();
        return $url . sprintf('channable/order/hook/store/%s/code/%s/ajax/true', $storeId, $token);
    }

    /**
     * @param $storeId
     *
     * @return string
     */
    public function getOrderStatusUrl($storeId)
    {
        $url = $this->storeManager->getStore($storeId)->getBaseUrl();
        $token = $this->generalHelper->getToken();
        return $url . sprintf('channable/order/status/code/%s', $token);
    }

    /**
     * @param      $type
     * @param null $storeId
     *
     * @return mixed
     */
    public function getNeedsTaxCalulcation($type, $storeId = null)
    {
        if ($type == 'shipping') {
            return $this->generalHelper->getStoreValue(self::XPATH_TAX_SHIPPING, $storeId);
        } else {
            return $this->generalHelper->getStoreValue(self::XPATH_TAX_PRICE, $storeId);
        }
    }

    /**
     * @param null $storeId
     *
     * @return mixed
     */
    public function getTaxClassShipping($storeId = null)
    {
        return $this->generalHelper->getStoreValue(self::XPATH_SHIPPING_TAX_CLASS, $storeId);
    }

    /**
     * @param null $storeId
     *
     * @return mixed
     */
    public function getShippingMethod($storeId = null)
    {
        return $this->generalHelper->getStoreValue(self::XPATH_SHIPPING_METHOD, $storeId);
    }

    /**
     * @param null $storeId
     *
     * @return array
     */
    public function getShippingCustomShippingMethods($storeId = null)
    {
        $shippingMethodCustom = $this->generalHelper->getStoreValue(self::XPATH_SHIPPING_CUSTOM, $storeId);
        $shippingMethodCustom = preg_replace('/\s+/', '', $shippingMethodCustom);
        $prioritizedMethods = array_flip(array_reverse(explode(';', $shippingMethodCustom)));
        return $prioritizedMethods;
    }

    /**
     * @param null $storeId
     *
     * @return mixed|string
     */
    public function getShippingMethodFallback($storeId = null)
    {
        if ($method = $this->generalHelper->getStoreValue(self::XPATH_SHIPPING_METHOD_FALLBACK, $storeId)) {
            return $method;
        }
        return 'flatrate_flatrate';
    }

    /**
     * @param $id
     * @param $data
     */
    public function addTolog($id, $data)
    {
        $this->generalHelper->addTolog('Order ' . $id, $data);
    }

    /**
     * @return mixed
     */
    public function isLoggingEnabled()
    {
        return $this->generalHelper->getStoreValue(self::XPATH_LOG);
    }

}
