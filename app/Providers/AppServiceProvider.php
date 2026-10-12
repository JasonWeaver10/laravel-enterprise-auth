<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Okta\Provider as OktaProvider;
use SocialiteProviders\Saml2\Provider as Saml2Provider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('okta', OktaProvider::class);
            $event->extendSocialite('saml2', Saml2Provider::class);
        });
    }
}
