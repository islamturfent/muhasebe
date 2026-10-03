<?php

declare(strict_types=1);

namespace Muh\Controllers;

use Muh\Core\Auth;
use Muh\Core\Controller;
use Muh\Core\Request;
use Muh\Core\Response;
use Muh\Services\GlobalSearchService;

final class SearchController extends Controller
{
    public function index(Request $request): Response
    {
        Auth::requireCan('dashboard.view');
        $query = (string) $request->query('q', '');
        $results = (new GlobalSearchService())->search($query);

        return $this->view('app.search', [
            'layout' => 'layouts.app',
            'query' => $query,
            'results' => $results,
        ]);
    }
}
