<?php

declare(strict_types=1);

namespace Awcode\ThaiPromptpay\Tests;

use Awcode\ThaiPromptpay\Laravel\Facades\ThaiPromptpay as ThaiPromptpayFacade;
use Awcode\ThaiPromptpay\Laravel\ThaiPromptpayServiceProvider;
use Awcode\ThaiPromptpay\ThaiPromptpay;
use Orchestra\Testbench\TestCase;

class LaravelIntegrationTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [ThaiPromptpayServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['ThaiPromptpay' => ThaiPromptpayFacade::class];
    }

    public function test_singleton_is_resolvable_from_container(): void
    {
        $instance = $this->app->make(ThaiPromptpay::class);

        $this->assertInstanceOf(ThaiPromptpay::class, $instance);
        $this->assertSame($instance, $this->app->make(ThaiPromptpay::class));
    }

    public function test_facade_resolves_and_builds_payload(): void
    {
        $payload = ThaiPromptpayFacade::phone('0899999999')->amount(420)->build();

        $this->assertSame(
            '00020101021229370016A000000677010111011300668999999995802TH53037645406420.006304CF9E',
            $payload->toString()
        );
    }

    public function test_config_is_merged(): void
    {
        $this->assertIsArray($this->app['config']->get('thaipromptpay'));
        $this->assertSame(300, $this->app['config']->get('thaipromptpay.qr.size'));
    }

    public function test_no_verifier_is_bound_when_default_is_unset(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('No default Verifier');
        $this->app->make(\Awcode\ThaiPromptpay\ThaiPromptpay::class)
            ->verify('00460006000001010301402051234X5102TH9104XXXX');
    }

    public function test_slipok_verifier_is_resolved_from_config(): void
    {
        $this->app['config']->set('thaipromptpay.verifier.default', 'slipok');
        $this->app['config']->set('thaipromptpay.verifier.providers.slipok.api_key', 'ABC');
        $this->app['config']->set('thaipromptpay.verifier.providers.slipok.branch_id', 'shop-1');

        // Bust any prior singleton so the new config is read.
        $this->app->forgetInstance(\Awcode\ThaiPromptpay\Slip\Verify\Verifier::class);
        $this->app->forgetInstance(\Awcode\ThaiPromptpay\ThaiPromptpay::class);

        $verifier = $this->app->make(\Awcode\ThaiPromptpay\Slip\Verify\Verifier::class);
        $this->assertInstanceOf(\Awcode\ThaiPromptpay\Slip\Verify\SlipOkVerifier::class, $verifier);
    }

    public function test_easyslip_verifier_is_resolved_from_config(): void
    {
        $this->app['config']->set('thaipromptpay.verifier.default', 'easyslip');
        $this->app['config']->set('thaipromptpay.verifier.providers.easyslip.api_key', 'tok');

        $this->app->forgetInstance(\Awcode\ThaiPromptpay\Slip\Verify\Verifier::class);
        $this->app->forgetInstance(\Awcode\ThaiPromptpay\ThaiPromptpay::class);

        $verifier = $this->app->make(\Awcode\ThaiPromptpay\Slip\Verify\Verifier::class);
        $this->assertInstanceOf(\Awcode\ThaiPromptpay\Slip\Verify\EasySlipVerifier::class, $verifier);
    }
}
