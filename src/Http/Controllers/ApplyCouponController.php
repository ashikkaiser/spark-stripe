<?php

namespace Spark\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Cashier\Subscription;
use Spark\HandlesCouponExceptions;
use Stripe\Exception\InvalidRequestException;
use Stripe\PromotionCode;

class ApplyCouponController
{
    use RetrievesBillableModels, HandlesCouponExceptions;

    /**
     * Update the receipt emails for the given billable.
     *
     * @param  \Illuminate\Http\Request
     * @return void
     * @throws \Illuminate\Validation\ValidationException
     */
    public function __invoke(Request $request)
    {
        $billable = $this->billable();

        $subscription = $billable->subscription('default');

        try {
            $this->applyCoupon($request->coupon, $billable, $subscription);
        } catch (InvalidRequestException $e) {
            if (in_array($e->getStripeParam(), ['coupon', 'promotion_code'])) {
                return $this->handleCouponException($e);
            }

            report($e);

            throw ValidationException::withMessages([
                '*' => __('An unexpected error occurred and we have notified our support team. Please try again later.')
            ]);
        }
    }

    /**
     * @param  Subscription|null  $subscription
     * @param  $coupon
     */
    protected function applyCoupon($coupon, $billable, ?Subscription $subscription): void
    {
        $codes = PromotionCode::all(['code' => $coupon], $billable->stripeOptions());

        if ($codes && $codes->first()) {
            $subscription->updateStripeSubscription([
                'promotion_code' => $codes->first()->id
            ]);
        } else {
            $subscription->updateStripeSubscription([
                'coupon' => $coupon
            ]);
        }
    }
}
