<?php

namespace Tests\AppBundle\Sylius\OrderProcessing;

use AppBundle\Entity\Contract;
use AppBundle\Entity\Restaurant;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\OrderItem;
use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Sylius\OrderProcessing\OrderOptionsProcessor;
use AppBundle\Sylius\Product\ProductOptionInterface;
use AppBundle\Sylius\Product\ProductOptionValueInterface;
use AppBundle\Sylius\Product\ProductVariantInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Prophecy\Argument;
use Sylius\Component\Order\Model\OrderInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class OrderOptionsProcessorTest extends KernelTestCase
{
    private $adjustmentFactory;
    private $orderOptionsProcessor;

    public function setUp()
    {
        parent::setUp();

        self::bootKernel();

        $this->adjustmentFactory = static::$kernel->getContainer()->get('sylius.factory.adjustment');
        $this->orderOptionsProcessor = new OrderOptionsProcessor($this->adjustmentFactory);
    }

    private function createProductOption($strategy, $price = null)
    {
        $option = $this->prophesize(ProductOptionInterface::class);
        $option
            ->getStrategy()
            ->willReturn($strategy);
        $option
            ->getPrice()
            ->willReturn($price);

        return $option->reveal();
    }

    private function createProductOptionValue(ProductOptionInterface $option, $value, $price = null)
    {
        $optionValue = $this->prophesize(ProductOptionValueInterface::class);
        $optionValue
            ->getOption()
            ->willReturn($option);
        $optionValue
            ->getValue()
            ->willReturn($value);
        $optionValue
            ->getPrice()
            ->willReturn($price);

        return $optionValue->reveal();
    }

    private function createProductVariant(array $optionValues = [])
    {
        $productVariant = $this->prophesize(ProductVariantInterface::class);
        $productVariant
            ->getOptionValues()
            ->willReturn(new ArrayCollection($optionValues));

        return $productVariant->reveal();
    }

    private function createOrderItem($total, ProductVariantInterface $variant)
    {
        $orderItem = new OrderItem();

        $orderItem->setVariant($variant);

        return $orderItem;
    }

    public function testOrderItemWithoutOptions()
    {
        $order = new Order();

        $productVariant = $this->createProductVariant();
        $order->addItem($this->createOrderItem(100, $productVariant));

        $this->orderOptionsProcessor->process($order);

        $adjustments = $order->getAdjustmentsRecursively(AdjustmentInterface::MENU_ITEM_MODIFIER_ADJUSTMENT);

        $this->assertCount(0, $adjustments);
    }

    public function testOrderItemWithOptionStrategyFree()
    {
        $order = new Order();

        $productOption = $this->createProductOption(ProductOptionInterface::STRATEGY_FREE);
        $productOptionValue = $this->createProductOptionValue($productOption, 'Foo');

        $productVariant = $this->createProductVariant([$productOptionValue]);
        $order->addItem($this->createOrderItem(100, $productVariant));

        $this->orderOptionsProcessor->process($order);

        $adjustments = $order->getAdjustmentsRecursively(AdjustmentInterface::MENU_ITEM_MODIFIER_ADJUSTMENT);

        $this->assertCount(1, $adjustments);
        $this->assertEquals(AdjustmentInterface::MENU_ITEM_MODIFIER_ADJUSTMENT, $adjustments->get(0)->getType());
        $this->assertEquals('Foo', $adjustments->get(0)->getLabel());
        $this->assertEquals(0, $adjustments->get(0)->getAmount());
        $this->assertFalse($adjustments->get(0)->isNeutral());
    }

    public function testOrderItemWithOptionStrategyOption()
    {
        $order = new Order();

        $productOption = $this->createProductOption(ProductOptionInterface::STRATEGY_OPTION, 100);
        $productOptionValue = $this->createProductOptionValue($productOption, 'Foo');

        $productVariant = $this->createProductVariant([$productOptionValue]);
        $order->addItem($this->createOrderItem(100, $productVariant));

        $this->orderOptionsProcessor->process($order);

        $adjustments = $order->getAdjustmentsRecursively(AdjustmentInterface::MENU_ITEM_MODIFIER_ADJUSTMENT);

        $this->assertCount(1, $adjustments);
        $this->assertEquals(AdjustmentInterface::MENU_ITEM_MODIFIER_ADJUSTMENT, $adjustments->get(0)->getType());
        $this->assertEquals('Foo', $adjustments->get(0)->getLabel());
        $this->assertEquals(100, $adjustments->get(0)->getAmount());
        $this->assertFalse($adjustments->get(0)->isNeutral());
    }

    public function testOrderItemWithOptionStrategyOptionValue()
    {
        $order = new Order();

        $productOption = $this->createProductOption(ProductOptionInterface::STRATEGY_OPTION_VALUE);

        $productOptionValue1 = $this->createProductOptionValue($productOption, 'Foo', 100);
        $productOptionValue2 = $this->createProductOptionValue($productOption, 'Bar', 0);

        $productVariant = $this->createProductVariant([$productOptionValue1, $productOptionValue2]);
        $order->addItem($this->createOrderItem(100, $productVariant));

        $this->orderOptionsProcessor->process($order);

        $adjustments = $order->getAdjustmentsRecursively(AdjustmentInterface::MENU_ITEM_MODIFIER_ADJUSTMENT);

        $this->assertCount(2, $adjustments);

        $this->assertEquals(AdjustmentInterface::MENU_ITEM_MODIFIER_ADJUSTMENT, $adjustments->get(0)->getType());
        $this->assertEquals('Foo', $adjustments->get(0)->getLabel());
        $this->assertEquals(100, $adjustments->get(0)->getAmount());
        $this->assertFalse($adjustments->get(0)->isNeutral());

        $this->assertEquals(AdjustmentInterface::MENU_ITEM_MODIFIER_ADJUSTMENT, $adjustments->get(1)->getType());
        $this->assertEquals('Bar', $adjustments->get(1)->getLabel());
        $this->assertEquals(0, $adjustments->get(1)->getAmount());
        $this->assertFalse($adjustments->get(1)->isNeutral());
    }
}