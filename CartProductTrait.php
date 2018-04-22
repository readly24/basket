<?php
/**
 * Created by PhpStorm.
 * User: Utkir
 * Date: 20-Apr-18
 * Time: 11:09
 */

namespace readly\basket;


use yii\base\Component;

trait CartProductTrait
{
    protected $_quantity;
    protected $_modification_id;

    public function getQuantity()
    {
        return $this->_quantity;
    }

    public function setQuantity($quantity)
    {
        $this->_quantity = $quantity;
    }

    public function setModification($modification_id){

        $this->_modification_id = $modification_id;
    }

    public function getModificationId(){

       return  $this->_modification_id;
    }
    /**
     * Default implementation for getCost function. Cost is calculated as price * quantity
     * @param bool $withDiscount
     * @return int
     */
    public function getCost($withDiscount = true)
    {
        /** @var Component|CartProductInterface|self $this */
        $cost = $this->getQuantity() * $this->getPrice();
       $costEvent = new CostCalculationEvent([
            'baseCost' => $cost,
        ]);
        if ($this instanceof Component)
            $this->trigger(CartProductInterface::EVENT_COST_CALCULATION, $costEvent);
        if ($withDiscount)
            $cost = max(0, $cost - $costEvent->discountValue);

        return $cost;
    }

}