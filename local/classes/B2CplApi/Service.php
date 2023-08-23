<?php

namespace WS\B2CplApi;

use Bitrix\Catalog\Model\Price;
use Bitrix\Main\Type\DateTime;
use CCatalogProduct;
use CEvent;
use CIBlockElement;
use CPrice;
use CUtil;
use WS_PSettings;
use Bitrix\Catalog;

/**
 * Class Service
 * @package WS\B2CplApi
 */
class Service {

    const API_REGION = 77;

    private $client;
    private $logResponse;
    private $errorsAddProducts;
    private $errorsUpdateProducts;

    /**
     * Service constructor.
     * @param Client $client
     * @param LogResponse $logResponse
     */
    public function __construct(Client $client, LogResponse $logResponse) {
        if (!$this->client) {
            $this->client = $client;
        }
        if (!$this->logResponse) {
            $this->logResponse = $logResponse;
        }
    }

    public function removalStock() {

        if (!$this->checkApi()) {
            return false;
        }

        $response = $this->client->getInfoStore(self::API_REGION);
        $result = json_decode($response, true);

        if (!$result['success']) {
            $this->notificationClientOfBadResponse($result['message']);
            return false;
        }

        $this->logResponse->saveResponseToFile($response);
        $data = $this->getResponse($result);
        $this->updateGoods($data);
    }

    private function getResponse($result) {
        $data = array();
        foreach ($result['store'] as $store) {
            if ($store['region'] != self::API_REGION) {
                continue;
            }
            foreach ($store['products'] as $product) {
                if ($product['quantity_calc'] > 0) {
                    $data[] = new Product($product);
                }
            }
        }
        return $data;
    }

    private function updateGoods($products) {
        $processedItems = array();
        /** @var Product $product */
        foreach ($products as $product) {
            if ($productId = $this->getIdByVendorCode($product->getVendorCode())) {
                $this->updateProduct($productId, $product);
            } else {
                $productId = $this->addProduct($product);
            }
            if ($productId) {
                $processedItems[] = $productId;
            }
        }

        $this->setQuantityExcessProducts($processedItems);
        $this->notificationClientOfErrorAddProducts();
        $this->notificationClientOfErrorUpdateProducts();
    }

    private function checkApi() {
        $response = $this->client->getPing();
        $result = json_decode($response, true);

        if (!$result['success']) {
            $this->notificationClientOfNotAvailApi($result['message']);
            return false;
        }
        return true;
    }

    private function getIdByVendorCode($vendorCode) {
        $select = array("ID", "IBLOCK_ID", "PROPERTY_CML2_ARTICLE");
        $filter = array(
            "IBLOCK_ID" => (int) WS_PSettings::getFieldValue("katalog_tovarov"),
            "=PROPERTY_CML2_ARTICLE" => $vendorCode
        );

        $elementsIterator = \CIBlockElement::GetList(
            array("ID" => "ASC"),
            $filter,
            false,
            false,
            $select
        );

        if ($element = $elementsIterator->Fetch()) {
            return $element['ID'];
        } else {
            return false;
        }
    }

    private function addProduct(Product $product) {
        $result = false;
        $name = $product->getName();
        $vendorCode = $product->getVendorCode();
        $arField = array(
            "NAME" => $name,
            "IBLOCK_ID" => (int) WS_PSettings::getFieldValue("katalog_tovarov"),
            "ACTIVE" => "N",
            "PROPERTY_VALUES" => array(
                "CML2_ARTICLE" => $vendorCode
            )
        );
        $arField["CODE"] = CUtil::translit(
            $name,
            "ru",
            $this->getParamsTranslit()
        );
        $iblockElement = new CIBlockElement;
        if($elementId = $iblockElement->Add($arField)) {
            $result = $elementId;
            \Bitrix\Catalog\Model\Product::add(
                array(
                    "ID" => $elementId,
                    "AVAILABLE" => "N",
                    "QUANTITY" => $product->getQuantity(),
                    "WEIGHT" => $product->getWeight() * 1000,
                    "WIDTH" => $product->getWidth(),
                    "LENGTH" => $product->getLength(),
                    "HEIGHT" => $product->getHeight(),
                )
            );

            $arFields = Array(
                "PRODUCT_ID" => $elementId,
                "CATALOG_GROUP_ID" => "1",
                "PRICE" => 0,
                "CURRENCY" => "RUB",
            );
            $res = CPrice::GetList(
                array(),
                array(
                    "PRODUCT_ID" => $elementId,
                    "CATALOG_GROUP_ID" => "1"
                )
            );

            if ($arr = $res->Fetch()) {
                Price::update($arr["ID"], $arFields);
            } else {
                Price::add($arFields);
            }
        } else {
            $this->errorsAddProducts[] = $vendorCode . ' (Ошибка: ' . $iblockElement->LAST_ERROR . ')';
        }
        return $result;
    }

    private function updateProduct($productId, \WS\B2CplApi\Product $product) {
        $iterator = Catalog\Model\Product::getList([
            'select' => [
                'ID', 'QUANTITY', 'QUANTITY_RESERVED'
            ],
            'filter' => ['=ID' => $productId]
        ]);
        $result = $iterator->fetch();
        unset($iterator);
        if (empty($result)) {
            return false;
        }

        $quantity = $product->getQuantity();
        if (intval($result['QUANTITY_RESERVED']) > 0) {
            $quantity = $quantity - $result['QUANTITY_RESERVED'];
        }

        $arFields = array(
            "QUANTITY" => $quantity,
            "WEIGHT" => $product->getWeight() * 1000,
            "WIDTH" => $product->getWidth(),
            "LENGTH" => $product->getLength(),
            "HEIGHT" => $product->getHeight(),
        );

        $result = false;

        $res = \Bitrix\Catalog\Model\Product::update(intval($productId), $arFields);
        if ($res->isSuccess()) {
            $result = true;
            $this->setSortCatalog($productId, $quantity);
        } else {
            $this->errorsUpdateProducts[] = $productId;
        }

        return $result;
    }

    private function setQuantityExcessProducts(array $processedItems) {
        if (count($processedItems) < 1) {
            return;
        }
        $select = array("ID", "IBLOCK_ID");
        $filter = array(
            "IBLOCK_ID" => (int) WS_PSettings::getFieldValue("katalog_tovarov"),
            "!ID" => $processedItems
        );

        $elementsIterator = \CIBlockElement::GetList(
            array("ID" => "ASC"),
            $filter,
            false,
            false,
            $select
        );

        while ($element = $elementsIterator->Fetch()) {
            $this->setZeroQuantityItem($element['ID']);
        }
    }

    private function setZeroQuantityItem($id) {
        $arFields = array(
            "QUANTITY" => 0
        );
        $res = \Bitrix\Catalog\Model\Product::update(intval($id), $arFields);
        $this->setSortCatalog($id, 0);
    }

    private function getParamsTranslit() {
        return array(
            "max_len" => "100",
            "change_case" => "L",
            "replace_space" => "_",
            "replace_other" => "_",
            "delete_repeat_replace" => "true",
            "use_google" => "false",
        );
    }

    private function notificationClientOfNotAvailApi($errorMessage) {
        $arEventFields = array(
            "DATE_TIME" => new DateTime(),
            "ERROR_MESSAGE" => $errorMessage
        );
        CEvent::Send(EVENT_TYPE_B2CPL_NOT_AVAILABLE, "hu", $arEventFields);
    }

    private function notificationClientOfBadResponse($errorMessage) {
        $arEventFields = array(
            "DATE_TIME" => new DateTime(),
            "ERROR_MESSAGE" => $errorMessage
        );
        CEvent::Send(EVENT_TYPE_B2CPL_BAD_RESPONSE, "hu", $arEventFields);
    }

    private function notificationClientOfErrorAddProducts() {
        if (count($this->errorsAddProducts) > 0) {
            $arEventFields = array(
                "DATE_TIME" => new DateTime(),
                "ERROR_MESSAGE" => implode(', ', $this->errorsAddProducts)
            );
            CEvent::Send(EVENT_TYPE_B2CPL_ERROR_ADD, "hu", $arEventFields);
        }
    }

    private function notificationClientOfErrorUpdateProducts() {
        if (count($this->errorsUpdateProducts) > 0) {
            $arEventFields = array(
                "DATE_TIME" => new DateTime(),
                "ERROR_MESSAGE" => implode(', ', $this->errorsUpdateProducts)
            );
            CEvent::Send(EVENT_TYPE_B2CPL_ERROR_UPDATE, "hu", $arEventFields);
        }
    }

    /**
     * @param $productId
     * @param $quantity
     */
    private function setSortCatalog($productId, $quantity) {
        $catalogSort = 1;
        if ($quantity < 1) {
            $catalogSort = 0;
        }

        CIBlockElement::SetPropertyValuesEx($productId, WS_PSettings::getFieldValue("katalog_tovarov"), array("CATALOG_SORT" => $catalogSort));
    }
}