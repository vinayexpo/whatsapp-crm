<?php

use App\Models\ApiConnection;
use App\Models\CommerceSetting;
use App\Models\Company;
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

it('does not expose platform Razorpay credentials to tenant checkout', function () {
    config(['services.razorpay.key_id' => 'rzp_test_key', 'services.razorpay.key_secret' => 'secret']);

    $resolver = app(PaymentDriverResolver::class);

    expect($resolver->availableMethods())->toBe(['cod', 'upi']);
});

it('offers gateway:whatsapp once whatsapp pay is enabled and configured', function () {
    $company = Company::factory()->create();
    $connection = ApiConnection::factory()->connected()->create(['company_id' => $company->id]);
    CommerceSetting::factory()->create(['company_id' => $company->id, 'api_connection_id' => $connection->id, 'currency' => 'INR', 'whatsapp_pay_enabled' => true, 'payment_configuration_name' => 'store', 'razorpay_key_id' => 'rzp_test_key', 'razorpay_key_secret' => 'secret']);

    $resolver = app(PaymentDriverResolver::class);

    expect($resolver->availableMethods($company->id))->toBe(['cod', 'upi', 'gateway:whatsapp']);
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
    $company = Company::factory()->create();
    $connection = ApiConnection::factory()->connected()->create(['company_id' => $company->id]);
    CommerceSetting::factory()->create(['company_id' => $company->id, 'api_connection_id' => $connection->id, 'currency' => 'INR', 'whatsapp_pay_enabled' => true, 'payment_configuration_name' => 'store', 'razorpay_key_id' => 'rzp_test_key', 'razorpay_key_secret' => 'secret']);

    $resolver = app(PaymentDriverResolver::class);

    expect($resolver->forMethod('gateway:whatsapp', $company->id))->toBeInstanceOf(WhatsAppPayPaymentService::class);
});
