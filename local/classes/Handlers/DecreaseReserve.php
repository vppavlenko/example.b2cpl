<?php
namespace WS\Handlers;

use Bitrix\Main\Application;
use Bitrix\Sale\BasketItem;
use Bitrix\Sale\Order;
use WS\Helpers\HelperB2cpl;
use WS\Tools\Events\CustomHandler;
use Bitrix\Catalog;

/**
 * @author Vladimir Pavlenko <v.pavlenko@wssupport.ru>
 */

class DecreaseReserve extends CustomHandler {

    const ADMIN_URL = '/bitrix/admin/sale_order.php';

    public function process() {
        $request = Application::getInstance()->getContext()->getRequest();
        $action = $request->get('action');
        $id = $request->get('ID');

        if (!$this->checkAction($action, $id)) {
            return false;
        }

        $filteredIds = $this->filterOrders($id);

        if (count($filteredIds) < 1) {
            return false;
        }

        $products = $this->getProducts($filteredIds);
        $this->decreaseReserve($products);
    }

    private function checkAction($action, $id) {
        global $APPLICATION;

        if(($_SERVER["REQUEST_METHOD"] == "POST") && ($action == "decrease_reserve") && is_array($id) && ($APPLICATION->GetCurPage() == self::ADMIN_URL)) {
            return true;
        }
        return false;
    }

    private function getProducts(array $orderIds) {
        $arProducts = array();
        foreach ($orderIds as $orderId) {
            $products = $this->getProductsByOrderId($orderId);
            $arProducts[$orderId] = $products;
        }
        return $arProducts;
    }

    private function getProductsByOrderId($orderId) {
        /** @var Order $order */
        $order = Order::load($orderId);
        $basket = $order->getBasket();

        $items = array();
        /** @var BasketItem $basketItem */
        foreach ($basket->getBasketItems() as $basketItem) {
            $item['productId'] = $basketItem->getProductId();
            $item['quantity'] = $basketItem->getQuantity();
            $items[] = $item;
        }
        return $items;
    }

    private function filterOrders(array $orderIds) {
        $filteredOrders = array();
        foreach ($orderIds as $orderId) {

            $objB2cpl = new HelperB2cpl();
            if ($objB2cpl->getOrderB2cpl($orderId)) {
                continue;
            }
            if ($this->isDecrement($orderId)) {
                continue;
            }
            $filteredOrders[] = $orderId;
        }
        return $filteredOrders;
    }

    private function isDecrement($orderId) {
        $isDecrement = false;

        /** @var Order $order */
        $order = Order::load($orderId);
        $propertyCollection = $order->getPropertyCollection();

        /** @var \Bitrix\Sale\PropertyValue $obProp */
        foreach ($propertyCollection as $obProp) {
            $arProp = $obProp->getProperty();
            if($arProp["CODE"] == "IS_DECREMENT_RESERVE") {
                $value = $obProp->getValue();
                if ($value == 'Y') {
                    $isDecrement = true;
                }
                break;
            }
        }
        return $isDecrement;
    }

    private function decreaseReserve(array $arProducts) {
        foreach ($arProducts as $orderId => $products) {
            foreach ($products as $product) {
                $iterator = Catalog\Model\Product::getList([
                    'select' => [
                        'ID', 'QUANTITY', 'QUANTITY_RESERVED'
                    ],
                    'filter' => ['=ID' => $product['productId']]
                ]);
                $result = $iterator->fetch();
                unset($iterator);
                if (empty($result) || (intval($result['QUANTITY_RESERVED']) < 1)) {
                    continue;
                }

                $reserved = intval($result['QUANTITY_RESERVED']) - $product['quantity'];
                $quantity = intval($result['QUANTITY']) + $product['quantity'];

                $arFields = array(
                    "QUANTITY" => intval($quantity),
                    "QUANTITY_RESERVED" => intval($reserved),
                );
                $res = \Bitrix\Catalog\Model\Product::update(intval($product['productId']), $arFields);
            }
            $this->setDecrementOrder($orderId);
        }
    }

    private function setDecrementOrder($orderId) {
        /** @var Order $order */
        $order = Order::load($orderId);
        $propertyCollection = $order->getPropertyCollection();
        /** @var \Bitrix\Sale\PropertyValue $obProp */
        foreach ($propertyCollection as $obProp) {
            $arProp = $obProp->getProperty();
            if($arProp["CODE"] == "IS_DECREMENT_RESERVE") {
                $obProp->setValue('Y');
                break;
            }
        }
        $order->save();
    }
}