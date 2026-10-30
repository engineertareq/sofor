<?php

namespace App\Http\Controllers;

use App\Enums\ExpenseCategory;
use App\Models\Tour;
use App\Models\TourExpense;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** What a tour cost its organiser: the other side of its account. */
class TourExpenseController extends Controller
{
    public function store(Request $request, Tour $tour): RedirectResponse
    {
        $this->authorize('manage', $tour);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'amount' => ['required', 'numeric', 'min:1', 'max:99999999'],
            'category' => ['required', Rule::enum(ExpenseCategory::class)],
            'spent_on' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:240'],
        ]);

        $tour->expenses()->create($data);

        return back()->with('status', __('Cost added.'));
    }

    public function destroy(Tour $tour, TourExpense $expense): RedirectResponse
    {
        $this->authorize('manage', $tour);
        $expense->delete();

        return back()->with('status', __('Cost removed.'));
    }
}
