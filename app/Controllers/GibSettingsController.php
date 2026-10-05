<?php

declare(strict_types=1);

namespace Muh\Controllers;

use Muh\Core\Auth;
use Muh\Core\Controller;
use Muh\Core\Request;
use Muh\Core\Response;
use Muh\Core\Session;
use Muh\Services\GibSettingService;

/**
 * GİB / e-Beyan ayarları (Faz 1): e-Beyan şifresi/Token, sağlayıcı ve uç URL.
 */
final class GibSettingsController extends Controller
{
    public function index(Request $request): Response
    {
        Auth::requireCan('dashboard.view');
        return $this->view('app.settings.gib', [
            'layout' => 'layouts.app',
            'settings' => GibSettingService::get(Auth::tenantId()),
        ]);
    }

    public function save(Request $request): Response
    {
        Auth::requireCan('dashboard.view');
        GibSettingService::save(Auth::tenantId(), $request->input());
        Session::flash('success', __('beyan.settings_saved'));
        return Response::redirect('/app/settings/gib');
    }
}
