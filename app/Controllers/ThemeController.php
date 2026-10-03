<?php

declare(strict_types=1);

namespace Muh\Controllers;

use Muh\Core\Controller;
use Muh\Core\Request;
use Muh\Core\Response;
use Muh\Core\Session;

/**
 * Light/dark theme toggle; preference persists in the session.
 */
final class ThemeController extends Controller
{
    public function toggle(Request $request): Response
    {
        $mode = $request->query('mode');
        if (in_array($mode, ['light', 'dark'], true)) {
            Session::set('theme', $mode);
        }
        $return = $request->query('return') ?: '/';
        return Response::redirect((string) $return);
    }
}
