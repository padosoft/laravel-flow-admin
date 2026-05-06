<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class OverviewController extends Controller
{
    public function index(): Response
    {
        return response(view('flow-admin::pages.overview')->render());
    }
}
