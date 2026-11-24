<?php

namespace App\Providers;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $defaultIdentity = [
            'site_name' => 'SPORT MANIA',
            'site_tagline' => 'DISTRO ADMIN',
            'site_code' => 'SM',
            'site_logo' => null,
        ];

        if (Schema::hasTable('site_settings')) {
            $identity = SiteSetting::getJson('site_identity',$defaultIdentity);
            View::share('siteIdentity', array_merge($defaultIdentity, is_array($identity) ?$identity : []));
        } else {
            View::share('siteIdentity', $defaultIdentity);
        }
    }
}