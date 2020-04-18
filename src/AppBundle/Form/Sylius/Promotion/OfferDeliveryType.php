<?php

namespace AppBundle\Form\Sylius\Promotion;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Sylius\Promotion\Action\DeliveryPercentageDiscountPromotionActionCommand;
use AppBundle\Sylius\Promotion\Checker\Rule\IsRestaurantRuleChecker;
use Ramsey\Uuid\Uuid;
use Sylius\Component\Promotion\Factory\PromotionCouponFactoryInterface;
use Sylius\Component\Promotion\Model\Promotion;
use Sylius\Component\Promotion\Model\PromotionAction;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OfferDeliveryType extends AbstractType
{
    private $promotionRuleFactory;
    private $promotionCouponFactory;

    public function __construct(
        FactoryInterface $promotionRuleFactory,
        PromotionCouponFactoryInterface $promotionCouponFactory)
    {
        $this->promotionRuleFactory = $promotionRuleFactory;
        $this->promotionCouponFactory = $promotionCouponFactory;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'form.offer_delivery.name.label',
                'help' => 'form.offer_delivery.name.help'
            ])
            ->add('couponCode', TextType::class, [
                'mapped' => false,
                'label' => 'form.offer_delivery.coupon_code.label',
                // 'help' => 'form.offer_delivery.name.help'
            ]);

        // private function isUsedCouponCode(string $code): bool
        // {
        //     return null !== $this->get('sylius.repository.promotion_coupon')->findOneBy(['code' => $code]);
        // }

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) use ($options) {

            $form = $event->getForm();
            $promotion = $event->getData();

            $couponCode = $form->get('couponCode')->getData();

            $promotion->setCouponBased(true);
            $promotion->setCode(Uuid::uuid4()->toString());
            $promotion->setPriority(1);

            $promotionCoupon = $this->promotionCouponFactory->createNew();
            $promotionCoupon->setCode($couponCode);
            // TODO Add checkbox
            $promotionCoupon->setPerCustomerUsageLimit(1);

            $promotion->addCoupon($promotionCoupon);

            $isRestaurantRule = $this->promotionRuleFactory->createNew();
            $isRestaurantRule->setType(IsRestaurantRuleChecker::TYPE);
            $isRestaurantRule->setConfiguration([
                'restaurant_id' => $options['local_business']->getId()
            ]);

            $promotion->addRule($isRestaurantRule);

            $promotionAction = new PromotionAction();
            $promotionAction->setType(DeliveryPercentageDiscountPromotionActionCommand::TYPE);
            $promotionAction->setConfiguration([
                'percentage' => 1.0,
                'decrase_platform_fee' => false,
            ]);

            $promotion->addAction($promotionAction);
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Promotion::class,
        ));

        $resolver->setRequired('local_business');
        $resolver->setAllowedTypes('local_business', LocalBusiness::class);
    }
}
