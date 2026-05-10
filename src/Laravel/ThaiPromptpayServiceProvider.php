<?php

declare(strict_types=1);

namespace Awcode\ThaiPromptpay\Laravel;

use Awcode\ThaiPromptpay\Slip\Verify\EasySlipVerifier;
use Awcode\ThaiPromptpay\Slip\Verify\SlipOkVerifier;
use Awcode\ThaiPromptpay\Slip\Verify\Verifier;
use Awcode\ThaiPromptpay\ThaiPromptpay;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class ThaiPromptpayServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/thaipromptpay.php', 'thaipromptpay');

        $this->app->singleton(Verifier::class, fn (Container $app) => $this->resolveVerifier($app));

        $this->app->singleton(ThaiPromptpay::class, function (Container $app) {
            $verifier = $this->shouldBindVerifier($app) ? $app->make(Verifier::class) : null;

            return new ThaiPromptpay($verifier);
        });

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
        return [ThaiPromptpay::class, Verifier::class, 'thaipromptpay'];
    }

    private function shouldBindVerifier(Container $app): bool
    {
        return is_string($app['config']->get('thaipromptpay.verifier.default'));
    }

    private function resolveVerifier(Container $app): Verifier
    {
        $config = $app['config']->get('thaipromptpay.verifier');
        $name = $config['default'] ?? null;

        if (! is_string($name) || $name === '') {
            throw new \LogicException(
                'thaipromptpay.verifier.default is not set; cannot resolve a Verifier.'
            );
        }

        $providers = $config['providers'] ?? [];
        $settings = $providers[$name] ?? null;

        if (! is_array($settings)) {
            throw new \LogicException("Unknown slip verifier provider: '{$name}'.");
        }

        return match ($name) {
            'slipok' => new SlipOkVerifier(
                apiKey: (string) ($settings['api_key'] ?? ''),
                branchId: (string) ($settings['branch_id'] ?? ''),
                logSlips: (bool) ($settings['log_slips'] ?? true),
            ),
            'easyslip' => new EasySlipVerifier(
                apiKey: (string) ($settings['api_key'] ?? ''),
                checkDuplicate: (bool) ($settings['check_duplicate'] ?? false),
            ),
            default => throw new \LogicException("Unsupported slip verifier provider: '{$name}'."),
        };
    }
}
