<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MaintenanceMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/** Admin -> Settings -> Maintenance: close the whole site to everyone but admins. */
class MaintenanceController extends Controller
{
    public function __construct(private readonly MaintenanceMode $maintenance) {}

    public function edit(): View
    {
        return view('admin.maintenance', ['maintenance' => $this->maintenance]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:on,off'],
            'message' => ['nullable', 'string', 'max:'.MaintenanceMode::MESSAGE_MAX],
        ], [
            'message.max' => 'Keep the message under '.MaintenanceMode::MESSAGE_MAX.' characters.',
        ]);

        if ($data['action'] === 'off') {
            $this->maintenance->disable();

            return back()->with('status', 'Maintenance mode is off. Sofor is open to everyone again.');
        }

        $wasActive = $this->maintenance->active();
        $this->maintenance->enable($request->user(), $data['message'] ?? null);

        return back()->with('status', $wasActive
            ? 'Maintenance message updated.'
            : 'Maintenance mode is on. Only administrators can use Sofor until you turn it off.');
    }

    /** The page visitors see, shown to the admin without closing anything. */
    public function preview(): Response
    {
        return response()->view('errors.503', ['preview' => true]);
    }
}
