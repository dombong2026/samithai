<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $available = array_keys(config('samith.locales', ['en'=>'English']));
        $explicit = $request->route('locale');
        $saved = $request->hasSession() ? $request->session()->get('samith_locale') : null;

        $locale = in_array($explicit,$available,true) ? $explicit : null;
        if (! $locale && in_array($saved,$available,true)) $locale = $saved;
        if (! $locale && config('samith.trust_cloudflare_country') === true) {
            $country = strtoupper((string) $request->header('CF-IPCountry',''));
            $suggested = config('samith.country_locale.'.$country);
            if (in_array($suggested,$available,true)) $locale = $suggested;
        }
        if (! $locale) $locale = $request->getPreferredLanguage($available) ?: 'en';
        app()->setLocale($locale);
        if ($explicit && in_array($explicit,$available,true) && $request->hasSession()) {
            $request->session()->put('samith_locale',$explicit);
        }
        if (! app()->runningUnitTests() && ! $explicit && in_array($saved,$available,true)
            && $request->isMethod('GET')) {
            $path = '/'.ltrim($request->path(),'/');
            if ($path === '/' || preg_match('#^/(prompts|categories|blog|policies|support-center)(/|$)#',$path)) {
                $target = '/'.$locale.($path === '/' ? '' : $path);
                if ($request->getQueryString()) $target .= '?'.$request->getQueryString();
                return redirect($target);
            }
        }
        return $next($request);
    }
}
