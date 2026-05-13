<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TenantAppendController extends Controller
{
    public function create(Request $request): Response
    {
        abort_unless($request->user()->can('create', Tenant::class), 403);

        return Inertia::render('Tenants/Append', []);
    }
}
