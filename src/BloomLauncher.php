<?php

namespace Doppar\Bloom;

use Doppar\Bloom\Factories\HasherFactory;
use Doppar\Bloom\Factories\PersisterFactory;
use Phaseolies\Launchers\GhostableLauncher;
use Phaseolies\Launchers\ServiceLauncher;

class BloomLauncher extends ServiceLauncher implements GhostableLauncher
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
    public function launch(): void
    {
        $this->publishes(
            [
                __DIR__ . "/config/bloom.php" => config_path("bloom.php"),
            ],
            "config",
        );
    }

    /**
     * Get the services that should ghost-load this provider.
     *
     * @return array<int, string>
     */
    public function ghosts(): array
    {
        return [
            'bloom.manager',
        ];
    }
}
