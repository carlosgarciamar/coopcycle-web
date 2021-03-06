<?php

namespace Tests\AppBundle\Sylius\OrderProcessing;

use AppBundle\Entity\Contract;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Entity\Restaurant;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Exception\ShippingAddressMissingException;
use AppBundle\Service\DeliveryManager;
use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Sylius\OrderProcessing\OrderFeeProcessor;
use Prophecy\Argument;
use Psr\Log\NullLogger;
use Sylius\Component\Order\Model\Adjustment;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Order\Model\OrderItemInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Translation\TranslatorInterface;

class OrderFeeProcessorTest extends KernelTestCase
{
    private $adjustmentFactory;
    private $orderFeeProcessor;

    public function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $this->translator = $this->prophesize(TranslatorInterface::class);

        $this->translator
            ->trans(Argument::type('string'))
            ->willReturn('Foo');

        $this->adjustmentFactory = static::$kernel->getContainer()->get('sylius.factory.adjustment');
        $this->deliveryManager = $this->prophesize(DeliveryManager::class);

        $this->orderFeeProcessor = new OrderFeeProcessor(
            $this->adjustmentFactory,
            $this->translator->reveal(),
            $this->deliveryManager->reveal(),
            new NullLogger()
        );
    }

    private static function createContract($flatDeliveryPrice, $customerAmount, $feeRate,
        $variableDeliveryPriceEnabled = false, $variableDeliveryPrice = null,
        $variableCustomerAmountEnabled = false, $variableCustomerAmount = null)
    {
        $contract = new Contract();
        $contract->setFlatDeliveryPrice($flatDeliveryPrice);
        $contract->setCustomerAmount($customerAmount);
        $contract->setFeeRate($feeRate);
        $contract->setVariableDeliveryPriceEnabled($variableDeliveryPriceEnabled);
        $contract->setVariableDeliveryPrice($variableDeliveryPrice);
        $contract->setVariableCustomerAmountEnabled($variableCustomerAmountEnabled);
        $contract->setVariableCustomerAmount($variableCustomerAmount);

        return $contract;
    }

    private function createOrderItem($total)
    {
        $orderItem = $this->prophesize(OrderItemInterface::class);
        $orderItem
            ->getTotal()
            ->willReturn($total);

        $orderItem
            ->setOrder(Argument::type(OrderInterface::class))
            ->shouldBeCalled();

        return $orderItem->reveal();
    }

    public function testOrderWithoutRestaurant()
    {
        $order = new Order();
        $order->addItem($this->createOrderItem(100));

        $this->orderFeeProcessor->process($order);

        $adjustments = $order->getAdjustments(AdjustmentInterface::FEE_ADJUSTMENT);

        $this->assertCount(0, $adjustments);
        $this->assertEquals(0, $order->getFeeTotal());
    }

    public function testOrderWithoutRestaurantFees()
    {
        $contract = self::createContract(0, 0, 0.00);

        $restaurant = new Restaurant();
        $restaurant->setContract($contract);

        $order = new Order();
        $order->setRestaurant($restaurant);
        $order->addItem($this->createOrderItem(1000));

        $this->orderFeeProcessor->process($order);

        $adjustments = $order->getAdjustments(AdjustmentInterface::FEE_ADJUSTMENT);

        $this->assertCount(1, $adjustments);
        $this->assertEquals(0, $order->getFeeTotal());
    }

    public function testOrderWithRestaurantFees()
    {
        $contract = self::createContract(0, 0, 0.25);

        $restaurant = new Restaurant();
        $restaurant->setContract($contract);

        $order = new Order();
        $order->setRestaurant($restaurant);
        $order->addItem($this->createOrderItem(1000));

        $this->orderFeeProcessor->process($order);

        $adjustments = $order->getAdjustments(AdjustmentInterface::FEE_ADJUSTMENT);

        $this->assertCount(1, $adjustments);
        $this->assertEquals(250, $order->getFeeTotal());
    }

    public function testOrderWithBusinessAmount()
    {
        $contract = self::createContract(500, 0, 0.00);

        $restaurant = new Restaurant();
        $restaurant->setContract($contract);

        $order = new Order();
        $order->setRestaurant($restaurant);
        $order->addItem($this->createOrderItem(1000));

        $this->orderFeeProcessor->process($order);

        $adjustments = $order->getAdjustments(AdjustmentInterface::FEE_ADJUSTMENT);

        $this->assertCount(1, $adjustments);
        $this->assertEquals(500, $order->getFeeTotal());
    }

    public function testOrderWithBusinessAmountAndCustomerAmount()
    {
        $contract = self::createContract(500, 500, 0.00);

        $restaurant = new Restaurant();
        $restaurant->setContract($contract);

        $order = new Order();
        $order->setRestaurant($restaurant);
        $order->addItem($this->createOrderItem(1000));

        $this->orderFeeProcessor->process($order);

        $this->assertEquals(1500, $order->getTotal());

        $adjustments = $order->getAdjustments(AdjustmentInterface::FEE_ADJUSTMENT);

        $this->assertCount(1, $adjustments);
        $this->assertEquals(500, $order->getFeeTotal());
    }

    public function testOrderWithBusinessAmountAndCustomerAmountAndTip()
    {
        $contract = self::createContract(500, 500, 0.00);

        $restaurant = new Restaurant();
        $restaurant->setContract($contract);

        $order = new Order();
        $order->setRestaurant($restaurant);
        $order->addItem($this->createOrderItem(1000));
        $order->setTipAmount(300);

        $this->orderFeeProcessor->process($order);

        $this->assertEquals(1800, $order->getTotal());

        $adjustments = $order->getAdjustments(AdjustmentInterface::FEE_ADJUSTMENT);

        $this->assertCount(1, $adjustments);
        $this->assertEquals(800, $order->getFeeTotal());
    }

    public function testOrderWithDeliveryPromotion()
    {
        $contract = self::createContract(565, 350, 0.1860);

        $restaurant = new Restaurant();
        $restaurant->setContract($contract);

        $order = new Order();
        $order->setRestaurant($restaurant);
        $order->addItem($this->createOrderItem(2500));

        $freeDeliveryAdjustment = new Adjustment();
        $freeDeliveryAdjustment->setType(AdjustmentInterface::DELIVERY_PROMOTION_ADJUSTMENT);
        $freeDeliveryAdjustment->setLabel('Free delivery');
        $freeDeliveryAdjustment->setAmount(-350);
        $freeDeliveryAdjustment->setOriginCode('FREE_DELIVERY');

        $order->addAdjustment($freeDeliveryAdjustment);

        $deliveryAdjustment = new Adjustment();
        $deliveryAdjustment->setType(AdjustmentInterface::DELIVERY_ADJUSTMENT);
        $deliveryAdjustment->setLabel('Delivery');
        $deliveryAdjustment->setAmount(350);

        $order->addAdjustment($deliveryAdjustment);

        $this->orderFeeProcessor->process($order);

        // The customer pays 25 (delivery is free)
        $this->assertEquals(2500, $order->getTotal());

        $feeAdjustments = $order->getAdjustments(AdjustmentInterface::FEE_ADJUSTMENT);
        $deliveryAdjustments = $order->getAdjustments(AdjustmentInterface::DELIVERY_ADJUSTMENT);

        $this->assertCount(1, $feeAdjustments);
        $this->assertEquals(680, $order->getFeeTotal());
    }

    public function testOrderWithVariableBusinessAmount()
    {
        $pricing = new PricingRuleSet();
        $contract = self::createContract(0, 0, 0.00, true, $pricing);

        $restaurant = new Restaurant();
        $restaurant->setContract($contract);

        $order = new Order();
        $order->setRestaurant($restaurant);
        $order->addItem($this->createOrderItem(1000));

        $delivery = new Delivery();

        $this->deliveryManager
            ->createFromOrder($order)
            ->willReturn($delivery);

        $this->deliveryManager
            ->getLastMatchedRule()
            ->willReturn(new PricingRule());

        $this->deliveryManager
            ->getPrice($delivery, $pricing)
            ->willReturn(750);

        $this->orderFeeProcessor->process($order);

        $adjustments = $order->getAdjustments(AdjustmentInterface::FEE_ADJUSTMENT);

        $this->assertCount(1, $adjustments);
        $this->assertEquals(750, $order->getFeeTotal());
    }

    public function testOrderWithVariableBusinessAmountFallback()
    {
        $pricing = new PricingRuleSet();
        $contract = self::createContract(350, 0, 0.00, true, $pricing);

        $restaurant = new Restaurant();
        $restaurant->setContract($contract);

        $order = new Order();
        $order->setRestaurant($restaurant);
        $order->addItem($this->createOrderItem(1000));

        $delivery = new Delivery();

        $this->deliveryManager
            ->createFromOrder($order)
            ->willReturn($delivery);

        $this->deliveryManager
            ->getPrice($delivery, $pricing)
            ->willReturn(null);

        $this->orderFeeProcessor->process($order);

        $adjustments = $order->getAdjustments(AdjustmentInterface::FEE_ADJUSTMENT);

        $this->assertCount(1, $adjustments);
        $this->assertEquals(350, $order->getFeeTotal());
    }

    public function testOrderWithVariableCustomerAmount()
    {
        $pricing = new PricingRuleSet();
        $contract = self::createContract(550, 0, 0.00, false, null, true, $pricing);

        $restaurant = new Restaurant();
        $restaurant->setContract($contract);

        $order = new Order();
        $order->setRestaurant($restaurant);
        $order->addItem($this->createOrderItem(1000));

        $delivery = new Delivery();

        $this->deliveryManager
            ->createFromOrder($order)
            ->willReturn($delivery);

        $this->deliveryManager
            ->getPrice($delivery, $pricing)
            ->willReturn(350);

        $this->deliveryManager
            ->getLastMatchedRule()
            ->willReturn(new PricingRule());

        $this->orderFeeProcessor->process($order);

        $feeAdjustments = $order->getAdjustments(AdjustmentInterface::FEE_ADJUSTMENT);
        $deliveryAdjustments = $order->getAdjustments(AdjustmentInterface::DELIVERY_ADJUSTMENT);

        $this->assertCount(1, $feeAdjustments);
        $this->assertCount(1, $deliveryAdjustments);

        $this->assertEquals(550, $order->getFeeTotal());
        $this->assertEquals(350, $order->getAdjustmentsTotal(AdjustmentInterface::DELIVERY_ADJUSTMENT));
    }

    public function testOrderWithVariableCustomerAmountAndMissingShippingAddress()
    {
        $pricing = new PricingRuleSet();
        $contract = self::createContract(350, 350, 0.00, false, null, true, $pricing);

        $restaurant = new Restaurant();
        $restaurant->setContract($contract);

        $order = new Order();
        $order->setRestaurant($restaurant);
        $order->addItem($this->createOrderItem(1000));

        $this->deliveryManager
            ->createFromOrder($order)
            ->willThrow(new ShippingAddressMissingException());

        $this->deliveryManager
            ->getPrice(
                Argument::type(Delivery::class),
                Argument::type(PricingRuleSet::class)
            )
            ->shouldNotBeCalled();

        $this->orderFeeProcessor->process($order);

        $feeAdjustments = $order->getAdjustments(AdjustmentInterface::FEE_ADJUSTMENT);
        $deliveryAdjustments = $order->getAdjustments(AdjustmentInterface::DELIVERY_ADJUSTMENT);

        $this->assertCount(1, $feeAdjustments);
        $this->assertCount(1, $deliveryAdjustments);

        $this->assertEquals(350, $order->getFeeTotal());
        $this->assertEquals(350, $order->getAdjustmentsTotal(AdjustmentInterface::DELIVERY_ADJUSTMENT));
    }
}
