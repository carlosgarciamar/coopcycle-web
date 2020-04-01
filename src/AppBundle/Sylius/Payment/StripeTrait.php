<?php

namespace AppBundle\Sylius\Payment;

use Stripe\Refund;
use Stripe\PaymentIntent;
use Stripe\Source;

trait StripeTrait
{
    public function getCharge()
    {
        if (isset($this->details['charge'])) {

            return $this->details['charge'];
        }
    }

    public function setCharge($charge)
    {
        $this->details = array_merge($this->details, ['charge' => $charge]);

        return $this;
    }

    public function setStripeUserId($stripeUserId)
    {
        $this->details = array_merge($this->details, ['stripe_user_id' => $stripeUserId]);
    }

    public function getStripeUserId()
    {
        if (isset($this->details['stripe_user_id'])) {
            return $this->details['stripe_user_id'];
        }
    }

    public function setStripeToken($stripeToken)
    {
        $this->details = array_merge($this->details, ['stripe_token' => $stripeToken]);
    }

    public function getStripeToken()
    {
        if (isset($this->details['stripe_token'])) {

            return $this->details['stripe_token'];
        }
    }

    public function setLastError($message)
    {
        $this->details = array_merge($this->details, ['last_error' => $message]);
    }

    public function getLastError()
    {
        if (isset($this->details['last_error'])) {

            return $this->details['last_error'];
        }
    }

    public function addRefund(Refund $refund)
    {
        $refunds = [];
        if (isset($this->details['refunds'])) {
            $refunds = $this->details['refunds'];
        }

        $refunds[] = [
            'id' => $refund->id,
            'amount' => $refund->amount,
        ];

        $this->details = array_merge($this->details, ['refunds' => $refunds]);
    }

    public function getRefunds()
    {
        if (isset($this->details['refunds'])) {

            return $this->details['refunds'];
        }

        return [];
    }

    public function getRefundTotal()
    {
        $total = 0;
        foreach ($this->getRefunds() as $refund) {
            $total += $refund['amount'];
        }

        return $total;
    }

    public function getRefundAmount()
    {
        return $this->getAmount() - $this->getRefundTotal();
    }

    public function setPaymentIntent(PaymentIntent $intent)
    {
        // Note that if your API version is before 2019-02-11, 'requires_action'
        // appears as 'requires_source_action'.

        $status = $intent->status;
        if ($intent->status === 'requires_source_action') {
            $status = 'requires_action';
        }

        $this->details = array_merge($this->details, [
            'payment_intent' => $intent->id,
            'payment_intent_client_secret' => $intent->client_secret,
            'payment_intent_status' => $status,
            'payment_intent_next_action' => $intent->next_action ? $intent->next_action->type : null
        ]);
    }

    public function getPaymentIntent()
    {
        if (isset($this->details['payment_intent'])) {

            return $this->details['payment_intent'];
        }
    }

    public function getPaymentIntentClientSecret()
    {
        if (isset($this->details['payment_intent_client_secret'])) {

            return $this->details['payment_intent_client_secret'];
        }
    }

    public function getPaymentIntentStatus()
    {
        if (isset($this->details['payment_intent_status'])) {

            return $this->details['payment_intent_status'];
        }
    }

    public function getPaymentIntentNextAction()
    {
        if (isset($this->details['payment_intent_next_action'])) {

            return $this->details['payment_intent_next_action'];
        }
    }

    public function setPaymentMethod($value)
    {
        $this->details = array_merge($this->details, [
            'payment_method' => $value,
        ]);
    }

    public function getPaymentMethod()
    {
        if (isset($this->details['payment_method'])) {

            return $this->details['payment_method'];
        }
    }

    public function requiresUseStripeSDK()
    {
        return $this->getPaymentIntentStatus() === 'requires_action' &&
            $this->getPaymentIntentNextAction() === 'use_stripe_sdk';
    }

    public function requiresCapture()
    {
        return $this->getPaymentIntentStatus() === 'requires_capture';
    }

    public function setSource(Source $source)
    {
        $this->details = array_merge($this->details, [
            'source' => $source->id,
            'source_client_secret' => $source->client_secret,
            'source_redirect_url' => $source->redirect->url,
        ]);
    }

    public function hasSource()
    {
        return isset($this->details['source']);
    }

    public function getSourceRedirectUrl()
    {
        if (isset($this->details['source_redirect_url'])) {

            return $this->details['source_redirect_url'];
        }
    }

    public function getSourceClientSecret()
    {
        if (isset($this->details['source_client_secret'])) {

            return $this->details['source_client_secret'];
        }
    }
}
