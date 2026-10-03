<?php

declare(strict_types=1);

namespace Muh\Controllers;

use Muh\Core\Controller;
use Muh\Core\Request;
use Muh\Core\Response;

/**
 * Settings landing page — links to the various settings sub-sections.
 */
final class SettingsController extends Controller
{
    public function index(Request $request): Response
    {
        $sections = [
            ['/app/settings/security', 'security.title', '🔐'],
            ['/app/settings/subscription', 'subscription.title', '💳'],
            ['/app/users', 'user.title', '👥'],
            ['/app/tax-rates', 'taxrate.title', '🧾'],
            ['/app/audit', 'audit.title', '📋'],
            ['/app/documents', 'document.title', '📁'],
        ];

        return $this->view('app.settings.index', [
            'layout' => 'layouts.app',
            'sections' => $sections,
        ]);
    }
}
