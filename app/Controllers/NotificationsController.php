<?php

declare(strict_types=1);

namespace Muh\Controllers;

use Muh\Core\Auth;
use Muh\Core\Controller;
use Muh\Core\Request;
use Muh\Core\Response;
use Muh\Core\Session;
use Muh\Services\NotificationService;

final class NotificationsController extends Controller
{
    public function index(Request $request): Response
    {
        Auth::requireCan('dashboard.view');
        $service = new NotificationService();
        // Auto-generate any pending notifications, then list them (paginated).
        $service->generate();
        $page = $service->list();
        return $this->view('app.notifications.index', [
            'layout' => 'layouts.app',
            'notifications' => $page['items'],
            'total' => $page['total'],
            'page' => $page['page'],
            'lastPage' => $page['lastPage'],
            'summary' => $service->summary(),
        ]);
    }

    public function markRead(Request $request, $id): Response
    {
        $id = (int) $id;
        Auth::requireCan('dashboard.view');
        (new NotificationService())->markRead($id);
        return Response::redirect('/app/notifications');
    }

    public function markAllRead(Request $request): Response
    {
        Auth::requireCan('dashboard.view');
        (new NotificationService())->markAllRead();
        return Response::redirect('/app/notifications');
    }
}
