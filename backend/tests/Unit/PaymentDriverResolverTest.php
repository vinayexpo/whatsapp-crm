<?php

use App\Services\Commerce\Payments\CodPaymentService;
use App\Services\Commerce\Payments\PaymentDriverResolver;
use App\Services\Commerce\Payments\UpiManualPaymentService;
use App\Services\Commerce\Payments\WhatsAppPayPaymentService;

beforeEach(function () {
    config([
        'services.razorpay.key_id' => null,
        'services.razorpay.key_secret' => null,
        'services.whatsapp_pay.enabled' => false,
        'services.whatsapp_pay.payment_configuration_name' => null,
    ]);
});

it('only offers cod and upi when razorpay and whatsapp pay are unconfigured', function () {
    $resolver = app(PaymentDriverResolver::class);

    expect($resolver->availableMethods())->toBe(['cod', 'upi']);
});

it('offers gateway:razorpay once razorpay credentials are configured', function () {
    config(['services.razorpay.key_id' => 'rzp_test_key', 'services.razorpay.key_secret' => 'secret']);

    $resolver = app(PaymentDriverResolver::class);

    expect($resolver->availableMethods())->toBe(['cod', 'upi', 'gateway:razorpay']);
});

it('offers gateway:whatsapp once whatsapp pay is enabled and configured', function () {
    config(['services.whatsapp_pay.enabled' => true, 'services.whatsapp_pay.payment_configuration_name' => 'default']);

    $resolver = app(PaymentDriverResolver::class);

    expect($resolver->availableMethods())->toBe(['cod', 'upi', 'gateway:whatsapp']);
});

it('resolves cod and upi drivers regardless of gateway configuration', function () {
    $resolver = app(PaymentDriverResolver::class);

    expect($resolver->forMethod('cod'))->toBeInstanceOf(CodPaymentService::class);
    expect($resolver->forMethod('upi'))->toBeInstanceOf(UpiManualPaymentService::class);
});

it('throws when explicitly resolving an unconfigured gateway method', function () {
    $resolver = app(PaymentDriverResolver::class);

    expect(fn () => $resolver->forMethod('gateway:razorpay'))->toThrow(InvalidArgumentException::class);
    expect(fn () => $resolver->forMethod('gateway:whatsapp'))->toThrow(InvalidArgumentException::class);
});

it('throws for an unknown payment method', function () {
    $resolver = app(PaymentDriverResolver::class);

    expect(fn () => $resolver->forMethod('bitcoin'))->toThrow(InvalidArgumentException::class);
});

it('resolves whatsapp pay once configured', function () {
    config(['services.whatsapp_pay.enabled' => true, 'services.whatsapp_pay.payment_configuration_name' => 'default']);

    $resolver = app(PaymentDriverResolver::class);

    expect($resolver->forMethod('gateway:whatsapp'))->toBeInstanceOf(WhatsAppPayPaymentService::class);
});
