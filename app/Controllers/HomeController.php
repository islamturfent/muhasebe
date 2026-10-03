<?php

declare(strict_types=1);

namespace Muh\Controllers;

use Muh\Core\Controller;
use Muh\Core\Request;
use Muh\Core\Response;
use Muh\Core\Session;
use Muh\Core\Translator;

/**
 * Public marketing / landing pages.
 */
final class HomeController extends Controller
{
    public function landing(Request $request): Response
    {
        return $this->view('landing.home');
    }

    public function pricing(Request $request): Response
    {
        $plans = \Muh\Models\Plan::allActive();
        return $this->view('landing.pricing', ['plans' => $plans]);
    }

    public function switchLocale(Request $request): Response
    {
        $allowed = Translator::instance()->supportedLocales();
        $locale = $request->query('locale');
        if (in_array($locale, $allowed, true)) {
            Session::set('locale', $locale);
        }
        return Response::redirect($request->query('return', '/'));
    }
}
