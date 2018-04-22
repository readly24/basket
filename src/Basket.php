<?php
namespace readly\basket;


use readly\basket\CartActionEvent;
use readly\basket\CartModificationInterface;
use readly\basket\CartProductInterface;
use readly\basket\CostCalculationEvent;
use Yii;
use yii\base\Component;
use yii\base\Event;
use yii\di\Instance;
use yii\helpers\ArrayHelper;
use yii\rest\Serializer;
use yii\web\Session;

/**
 * Class ReadlyCart
 * @property CartProductInterface[] $items
 * @property int $count Total count of items in the cart
 * @property int $cost Total cost of items in the cart
 * @property bool $isEmpty Returns true if cart is empty
 * @property string $hash Returns hash (md5) of the current cart, that is uniq to the current combination
 * of items, quantities and costs
 * @property string $serialized Get/set serialized content of the cart
 * @package \frontend\modules\readly\components
 */
class Basket extends Component
{

    /** Triggered on product put */
    const EVENT_PRODUCT_ADD = 'putProduct';

    const EVENT_PRODUCT_UPDATE = 'updateProduct';
    /** Triggered on after product remove */
    const EVENT_BEFORE_PRODUCT_REMOVE = 'removeProduct';
    /** Triggered on any cart change: add, update, delete product */
    const EVENT_CART_CHANGE = 'cartChange';
    /** Triggered on after cart cost calculation */
    const EVENT_COST_CALCULATION = 'costCalculation';

    /**
     * If true (default) cart will be automatically stored in and loaded from session.
     * If false - you should do this manually with saveToSession and loadFromSession methods
     * @var bool
     */
    public $storeInSession = true;

    /**
     * Session component
     * @var string|Session
     */

    public $session = 'session';

    /**
     * Readly cart ID to support multiple carts
     * @var string
     */

    public $className = __CLASS__;

    /**
     * @var CartProductInterface[][]
     */

    protected $_items= [];

    public function init()
    {
        if ($this->storeInSession)
            $this->loadFromSession();
    }

    /**
     * Loads cart from session
     */
    public function loadFromSession(){

        $this->session = Instance::ensure($this->session, Session::className());
        if (isset($this->session[$this->className]))
            $this->setSerialized($this->session[$this->className]);
    }

    /**
     * Saves cart to the session
     */
    public function saveToSession()
    {
        $this->session = Instance::ensure($this->session, Session::className());
        $this->session[$this->className] = $this->getSerialized();
    }

    /**
     * Sets cart from serialized string
     * @param string $serialized
     */
    public function setSerialized($serialized){
        $this->_items = unserialize($serialized);
    }

    /**
     * @param CartProductInterface $product
     * @param CartModificationInterface $modification
     * @param int $quantity
     */
    public function add($product,$modification, $quantity=1)
    {

        if (isset($this->_items[$product->getId()][$modification->getId()])) {
            $this->_items[$product->getId()][$modification->getId()]
                ->setQuantity($this->_items[$product->getId()][$modification->getId()]->getQuantity() + $quantity);
            $this->_items[$product->getId()][$modification->getId()]->setModification($modification->getId());
        } else {
            $product->setQuantity($quantity);
            $product->setModification($modification->getId());
            $this->_items[$product->getId()][$modification->getId()] = $product;
        }

        $this->trigger(self::EVENT_PRODUCT_ADD, new CartActionEvent([
            'action' => CartActionEvent::ACTION_PRODUCT_ADD,
            'product' => $this->_items[$product->getId()][$modification->getId()],
        ]));

        $this->trigger(self::EVENT_CART_CHANGE, new CartActionEvent([
            'action' => CartActionEvent::ACTION_PRODUCT_ADD,
            'product' => $this->_items[$product->getId()][$modification->getId()],
        ]));

        if ($this->storeInSession)
            $this->saveToSession();

    }

    /**
     * Returns cart items as serialized items
     * @return string
     */
    public function getSerialized()
    {
        return serialize($this->_items);
    }

    /**
     * @param CartProductInterface $product
     * @param CartModificationInterface $modification
     * @param int $quantity
     */
    public function update($product, $modification, $quantity){

        if ($quantity <= 0) {
            $this->remove($product,$modification);
            return;
        }
        if (isset($this->_items[$product->getId()][$modification->getId()])) {
            $this->_items[$product->getId()][$modification->getId()]->setQuantity($quantity);
        } else {
            $product->setQuantity($quantity);
            $this->_items[$product->getId()][$modification->getId()] = $product;
        }

        $this->trigger(self::EVENT_PRODUCT_UPDATE, new CartActionEvent([
            'action' => CartActionEvent::ACTION_UPDATE,
            'product' => $this->_items[$product->getId()][$modification->getId()],
        ]));
        $this->trigger(self::EVENT_CART_CHANGE, new CartActionEvent([
            'action' => CartActionEvent::ACTION_UPDATE,
            'product' => $this->_items[$product->getId()][$modification->getId()],
        ]));

        if ($this->storeInSession)
            $this->saveToSession();

    }

    /**
     * Removes position from the cart
     * @param CartProductInterface $product
     * @param CartModificationInterface $modification
     */
    public function remove($product, $modification)
    {
        $this->removeById($product->getId(),$modification->getId());
    }

    /**
     * Removes product from the cart by ID
     * @param string $id
     */
    public function removeById($product_id,$modification_id)
    {
        $this->trigger(self::EVENT_BEFORE_PRODUCT_REMOVE, new CartActionEvent([
            'action' => CartActionEvent::ACTION_BEFORE_REMOVE,
            'product' => $this->_items[$product_id][$modification_id],
        ]));
        $this->trigger(self::EVENT_CART_CHANGE, new CartActionEvent([
            'action' => CartActionEvent::ACTION_BEFORE_REMOVE,
            'product' => $this->_items[$product_id][$modification_id],
        ]));

        unset($this->_items[$product_id][$modification_id]);

        if(count($this->_items[$product_id]) == 0) {
            unset($this->_items[$product_id]);
        }

        if ($this->storeInSession)
            $this->saveToSession();
    }

    /**
     * Remove all products
     */
    public function removeAll()
    {
        $this->_items = [];

        $this->trigger(self::EVENT_CART_CHANGE, new CartActionEvent([
            'action' => CartActionEvent::ACTION_REMOVE_ALL,
        ]));

        if ($this->storeInSession)
            $this->saveToSession();
    }

    /**
     * Returns product by it's id. Null is returned if position was not found
     * @param string $id
     * @param string $modification_id
     * @return CartProductInterface|null
     */
    public function getProductById($id,$modification_id)
    {
        if ($this->hasProduct($id,$modification_id))
            return $this->_items[$id][$modification_id];
        else
            return null;
    }

    /**
     * Checks whether cart product exists or not
     * @param string $id
     * @param string $modification_id
     * @return bool
     */
    public function hasProduct($id,$modification_id)
    {
        return isset($this->_items[$id][$modification_id]);
    }

    /**
     * @return CartProductInterface[][]
     */
    public function getItems()
    {
        $items =[];
        foreach ($this->_items as $products) {
            foreach ($products as $product) {
                $items[] = $product;
            }
        }
        return $items;
    }

    /**
     * @param CartProductInterface[] $products
     */
   /* public function setProducts($products)
    {
        $this->_items = array_filter($products, function (CartProductInterface $product) {
            return $product->getQuantity() > 0;
        });
        $this->trigger(self::EVENT_CART_CHANGE, new CartActionEvent([
            'action' => CartActionEvent::ACTION_SET_PRODUCTS,
        ]));
        if ($this->storeInSession)
            $this->saveToSession();
    }*/

    /**
     * Returns true if cart is empty
     * @return bool
     */
    public function getIsEmpty()
    {
        return count($this->_items) == 0;
    }


    /**
     * @return int
     */
    public function getCount(){

        $count = 0;
        foreach ($this->_items as $products) {
            foreach ($products as $product) {
                $count += $product->getQuantity();
            }
        }
            return $count;

    }

    /**
     * Return full cart cost as a sum of the individual items costs
     * @param $withDiscount
     * @return int
     */
    public function getCost($withDiscount = false)
    {
        $cost = 0;
        foreach ($this->_items as $products) {
            foreach ($products as $product) {
                $cost += $product->getCost($withDiscount);
            }
        }

        $costEvent = new CostCalculationEvent([
            'baseCost' => $cost,
        ]);
        $this->trigger(self::EVENT_COST_CALCULATION, $costEvent);

        if ($withDiscount)
            $cost = max(0, $cost - $costEvent->discountValue);

        return $cost;
    }

    /**
     * Returns hash (md5) of the current cart, that is unique to the current combination
     * of products, quantities and costs. This helps us fast compare if two carts are the same, or not, also
     * we can detect if cart is changed (comparing hash to the one's saved somewhere)
     * @return string
     */
    public function getHash()
    {
        $data = [];
        foreach ($this->_items as $products) {
            foreach ($products as $product) {
                $data[] = [$product->getId(),$product->getModificationId(), $product->getQuantity(), $product->getPrice()];
            }
        }
        return md5(serialize($data));
    }



}