<?php

namespace App\Providers;

use App\Models\Admin;
use App\Models\Shop;
use App\Models\User;
use App\Services\Sms\FakeSmsGateway;
use App\Services\Sms\LogSmsGateway;
use App\Services\Sms\SmsGateway;
use App\Support\ApiDocs\ApiDocSections;
use App\Support\Money;
use Dedoc\Scramble\Scramble;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SmsGateway::class, function () {
            $driver = config('services.sms.driver', 'log');

            if ($driver === 'fake') {
                return new FakeSmsGateway;
            }

            return new LogSmsGateway;
        });
    }

    public function boot(): void
    {
        Relation::morphMap([
            'user' => User::class,
            'admin' => Admin::class,
            'shop' => Shop::class,
        ]);

        Blade::directive('money', fn (string $expression) => "<?php echo e(\\App\\Support\\Money::format({$expression})); ?>");
        Blade::directive('qty', fn (string $expression) => "<?php echo e(\\App\\Support\\Money::quantity({$expression})); ?>");

        Blade::if('admin', fn () => auth('admin')->check());
        Schema::defaultStringLength(191);

        // API docs (/docs/api): Super Admin / Company Admin / User sections, split by Public vs Token Required.
        Scramble::configure()
            ->withOperationTransformers(ApiDocSections::operation(...))
            ->withDocumentTransformers(ApiDocSections::document(...));
    }
}
