<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\StreamsMedia;
use App\Models\Expense;
use App\Models\Trip;
use App\Services\ReceiptService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Receipt photos, streamed from the private media disk to travellers on the
 * trip only.
 */
class ReceiptController extends Controller
{
    use StreamsMedia;

    public function __invoke(Trip $trip, Expense $expense): StreamedResponse
    {
        $this->authorize('view', $trip);

        abort_unless($expense->trip_id === $trip->id && $expense->receipt_path !== null, 404);

        return $this->streamMedia(ReceiptService::disk(), $expense->receipt_path, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline; filename="receipt-'.$expense->id.'.webp"',
        ]);
    }
}
