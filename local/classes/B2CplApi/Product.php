<?php
namespace WS\B2CplApi;


/**
 * Class Product
 */
class Product {

    /** @var string */
    private $vendorCode;
    /** @var string */
    private $name;
    /** @var integer */
    private $quantity;
    /** @var float */
    private $weight;
    /** @var float */
    private $width;
    /** @var float */
    private $height;
    /** @var float */
    private $length;

    /**
     * Product constructor.
     * @param $content
     */
    public function __construct($content) {
        $this->vendorCode = $content['prodcode'];
        $this->name = $content['prodname'];
        $this->quantity = $content['quantity_calc'];
        $this->weight = $content['prod_weight'];
        $this->width = $content['prod_width'];
        $this->height = $content['prod_height'];
        $this->length = $content['prod_length'];
    }

    public function getVendorCode() {
        return $this->vendorCode;
    }

    public function getName() {
        return $this->name;
    }

    public function getQuantity() {
        return $this->quantity;
    }

    public function getWeight() {
        return $this->weight;
    }

    public function getWidth() {
        return $this->width;
    }

    public function getHeight() {
        return $this->height;
    }

    public function getLength() {
        return $this->length;
    }
}
