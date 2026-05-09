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
}
