<?php

namespace Doppar\Bloom;

use Doppar\Bloom\Factories\HasherFactory;
use Doppar\Bloom\Factories\PersisterFactory;
use Phaseolies\Providers\ServiceProvider;

class BloomServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton("bloom.manager", function () {
            $persisterFactory = new PersisterFactory();
            $hasherFactory = new HasherFactory();

            return new BloomManager($persisterFactory, $hasherFactory);
        });

        $this->mergeConfig(__DIR__ . "/config/bloom.php", "bloom");
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->publishes(
            [
                __DIR__ . "/config/bloom.php" => config_path("bloom.php"),
            ],
            "config",
        );
    }
}
