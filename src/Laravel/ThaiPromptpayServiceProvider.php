<?php

declare(strict_types=1);

namespace Awcode\ThaiPromptpay\Laravel;

use Awcode\ThaiPromptpay\ThaiPromptpay;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class ThaiPromptpayServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/thaipromptpay.php', 'thaipromptpay');

        $this->app->singleton(ThaiPromptpay::class, fn () => new ThaiPromptpay());
        $this->app->alias(ThaiPromptpay::class, 'thaipromptpay');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/thaipromptpay.php' => $this->app->configPath('thaipromptpay.php'),
            ], 'thaipromptpay-config');
        }
    }

    public function provides(): array
    {
        return [ThaiPromptpay::class, 'thaipromptpay'];
    }
}
