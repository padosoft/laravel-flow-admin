<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\View\View;

class OverviewController extends Controller
{
    public function index(): View
    {
        return view('flow-admin::pages.overview');
    }
}
