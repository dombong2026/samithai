<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LanguageController extends Controller
{
    public function switch(Request $request, string $locale): RedirectResponse
    {
        abort_unless(array_key_exists($locale,config('samith.locales')),404);
        $request->session()->put('samith_locale',$locale);
        $previous = url()->previous();
        $path = parse_url($previous,PHP_URL_PATH) ?: '/';
        $host = parse_url($previous,PHP_URL_HOST);
        if ($host !== $request->getHost()) $path = '/';
        $path = preg_replace('#^/(en|zh|ko|ja|de|km|vi|th|id|ms|ar|fr|es)(?=/|$)#','',$path);
        $path = $path === '' ? '/' : $path;
        if ($path === '/' || preg_match('#^/(prompts|categories|blog|policies|support-center)(/|$)#', $path)) {
            return redirect('/'.$locale.($path === '/' ? '' : $path));
        }
        return redirect($path);
    }
}
