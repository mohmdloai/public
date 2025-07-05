<?php

interface ApplicableToBeShipped
{
    public function getName(): string;
    public function getWeight(): float;
}

interface MayExpire
{
    public function isExpired(): bool;
}

class Product
{
    protected string $name;
    protected float $price;
    protected int $stock;

    public function __construct(string $name, float $price, int $stock)
    {
        $this->name = $name;
        $this->price = $price;
        $this->stock = $stock;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function getStock(): int
    {
        return $this->stock;
    }

    public function reduceStock(int $qty): void
    {
        if ($qty > $this->stock) {
            throw new Exception("Not enough stock for {$this->name}");
        }
        $this->stock -= $qty;
    }
}

class ExpirableProduct extends Product implements MayExpire
{
    private DateTime $expirationDate;

    public function __construct(string $name, float $price, int $stock, DateTime $expirationDate)
    {
        parent::__construct($name, $price, $stock);
        $this->expirationDate = $expirationDate;
    }

    public function isExpired(): bool
    {
        return $this->expirationDate < new DateTime();
    }
}

class ShippableProduct extends Product implements ApplicableToBeShipped
{
    private float $weight;

    public function __construct(string $name, float $price, int $stock, float $weight)
    {
        parent::__construct($name, $price, $stock);
        $this->weight = $weight;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getWeight(): float
    {
        return $this->weight;
    }
}

class ExpirableShippableProduct extends ExpirableProduct implements ApplicableToBeShipped
{
    private float $weight;

    public function __construct(string $name, float $price, int $stock, DateTime $expirationDate, float $weight)
    {
        parent::__construct($name, $price, $stock, $expirationDate);
        $this->weight = $weight;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getWeight(): float
    {
        return $this->weight;
    }
}

class Cart
{
    private array $items = [];

    public function add(Product $product, int $qty): void
    {
        if ($product instanceof MayExpire && $product->isExpired()) {
            throw new Exception("Cannot add expired product: {$product->getName()}");
        }

        if ($qty > $product->getStock()) {
            throw new Exception("Cannot add $qty units of {$product->getName()}, only {$product->getStock()} in stock.");
        }

        $key = spl_object_hash($product);
        if (isset($this->items[$key])) {
            $this->items[$key]['qty'] += $qty;
        } else {
            $this->items[$key] = ['product' => $product, 'qty' => $qty];
        }
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    public function getSubtotal(): float
    {
        $sum = 0;
        foreach ($this->items as $entry) {
            $sum += $entry['product']->getPrice() * $entry['qty'];
        }
        return $sum;
    }

    public function getShippingWeight(): float
    {
        $weight = 0;
        foreach ($this->items as $entry) {
            $p = $entry['product'];
            if ($p instanceof ApplicableToBeShipped) {
                $weight += $p->getWeight() * $entry['qty'];
            }
        }
        return $weight;
    }

    public function checkout(Customer $customer): void
    {
        if ($this->isEmpty()) {
            throw new Exception("Cart is empty.");
        }

        // Check on stock and date of expiration
        foreach ($this->items as $entry) {
            $p = $entry['product'];
            $qty = $entry['qty'];
            if ($p instanceof MayExpire && $p->isExpired()) {
                throw new Exception("Product { $p->getName() } is expired.");
            }
            if ($qty > $p->getStock()) {
                throw new Exception("Not enough stock for { $p->getName() }.");
            }
        }

        $subtotal = $this->getSubtotal();
        $shipping = $this->getShippingWeight() > 0 ? 25 : 0;
        $total = $subtotal + $shipping;

        $customer->pay($total);

        if ($shipping > 0) {
            echo "** Shipment notice **<br>";
            foreach ($this->items as $entry) {
                $p = $entry['product'];
                $qty = $entry['qty'];
                if ($p instanceof ApplicableToBeShipped) {
                    $w = $p->getWeight() * 1000;
                    echo "{$qty}x {$p->getName()} {$w}g <br>";
                }
            }
            echo sprintf("Total package weight %.1fkg <br>", $this->getShippingWeight());
        }

        echo "** Checkout receipt **\n";
        foreach ($this->items as $entry) {
            $p = $entry['product'];
            $qty = $entry['qty'];
            echo "{$qty}x {$p->getName()} " . ($p->getPrice() * $qty) . "<br>";
        }
        echo "<br> ---------------------- <br>";
        echo "<br> Subtotal $subtotal ";
        echo " <br> Shipping $shipping";
        echo "<br> Amount $total";
        echo "<br> Balance left " . $customer->getBalance() . "\n";

        // Deduct from stock
        foreach ($this->items as $entry) {
            $entry['product']->reduceStock($entry['qty']);
        }
    }
}


class Customer
{
    private string $name;
    private float $balance;

    public function __construct(string $name, float $balance)
    {
        $this->name = $name;
        $this->balance = $balance;
    }

    public function pay(float $amount): void
    {
        if ($amount > $this->balance) {
            throw new Exception("Insufficient balance.");
        }
        $this->balance -= $amount;
    }

    public function getBalance(): float
    {
        return $this->balance;
    }
}


try {
    $cheese = new ExpirableShippableProduct('Cheese', 30, 5, new DateTime('2025-08-01'), 0.4);
    $biscuits = new ExpirableShippableProduct('Biscuits', 10, 2, new DateTime('2025-08-10'), 0.7);
    $tv = new ShippableProduct('TV', 7000, 1, 8.0);
    $scratchCard = new Product('Scratch Card', 75, 3);

    $customer = new Customer('me', 10000);
    $cart = new Cart();

    $cart->add($cheese, 2);
    $cart->add($biscuits, 1);
    $cart->add($scratchCard, 1);
    $cart->add($tv,1);

    $cart->checkout($customer);
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
}
