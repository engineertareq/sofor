<?php

namespace App\Services;

use App\Models\Sponsor;
use App\Models\SponsorInquiry;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;

/**
 * Reads for places that show sponsors on every visit (the home page, the
 * console menu). Until the sponsors migration has run they quietly show
 * nothing instead of taking the page down.
 */
class SponsorShowcase
{
    /** @return Collection<int, Sponsor> live sponsors, highest tier first */
    public function live(): Collection
    {
        try {
            return Sponsor::query()->live()->showcase()->get();
        } catch (QueryException) {
            return new Collection;
        }
    }

    /** Requests nobody has replied to yet, for the console menu badge. */
    public function unanswered(): int
    {
        try {
            return SponsorInquiry::query()->unanswered()->count();
        } catch (QueryException) {
            return 0;
        }
    }

    /** @return Collection<int, SponsorInquiry> the newest unanswered requests, for the console dashboard */
    public function latestUnanswered(int $limit = 4): Collection
    {
        try {
            return SponsorInquiry::query()->unanswered()->latest()->take($limit)->get();
        } catch (QueryException) {
            return new Collection;
        }
    }
}
