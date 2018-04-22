<?php

namespace readly\basket;


use yii\base\Event;


/**
 * Class CartActionEvent
 */
class CartActionEvent extends Event
{
    const ACTION_UPDATE = 'update';
    const ACTION_PRODUCT_ADD = 'productAdd';
    const ACTION_BEFORE_REMOVE = 'beforeRemove';
    const ACTION_REMOVE_ALL = 'removeAll';
    const ACTION_SET_PRODUCTS = 'setProducts';

    /**
     * Name of the action taken on the cart
     * @var string
     */
    public $action;
    /**
     * Product of the cart that was affected. Could be null if action deals with all products of the cart
     * @var CartProductInterface
     */
    public $product;

}