<?php
/**
 * Created by PhpStorm.
 * User: Utkir
 * Date: 20-Apr-18
 * Time: 11:59
 */

namespace readly\basket;


interface CartModificationInterface
{
    /** Triggered on cost calculation */
    const EVENT_COST_CALCULATION = 'costCalculation';

    /**
     * @return integer
     */
    public function getPrice();

    /**
     * @param bool $withDiscount
     * @return integer
     */
    public function getCost($withDiscount = true);

    /**
     * @return string
     */
    public function getId();

    /**
     * @param int $quantity
     */
    public function setQuantity($quantity);

    /**
     * @return int
     */
    public function getQuantity();

    /**
     * @return int
     */
    public function setModification($modification_id);


}