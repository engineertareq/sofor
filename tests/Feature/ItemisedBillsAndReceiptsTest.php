<?php

namespace Tests\Feature;

use App\Enums\SplitType;
use App\Models\Expense;
use App\Models\Trip;
use App\Models\TripMember;
use App\Models\User;
use App\Services\ReceiptService;
use App\Services\TripInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Restaurant bills split by what each person had (VAT and service spread in
 * proportion), and photos of the bill kept privately on each cost.
 */
class ItemisedBillsAndReceiptsTest extends TestCase
{
    use RefreshDatabase;

    private Trip $trip;

    private User $leader;

    private User $asha;

    /** @var array<string, TripMember> */
    private array $members;

    protected function setUp(): void
    {
        parent::setUp();

        config(['media.disks.private' => 'local']);
        Storage::fake('local');

        $this->leader = User::factory()->create(['name' => 'Rifat Hasan']);
        $this->asha = User::factory()->create(['name' => 'Asha Rahman']);
        $this->trip = Trip::factory()->create(['user_id' => $this->leader->id]);

        $invitations = app(TripInvitationService::class);
        $invitations->ensureOwnerMember($this->trip, $this->leader);
        $invitations->invite($this->trip, $this->leader, ['name' => $this->asha->name, 'email' => $this->asha->email]);
        $invitations->invite($this->trip, $this->leader, ['name' => 'Bilal Khan']);

        $this->members = [
            'leader' => $this->trip->members()->where('user_id', $this->leader->id)->first(),
            'asha' => $this->trip->members()->where('user_id', $this->asha->id)->first(),
            'bilal' => $this->trip->members()->where('name', 'Bilal Khan')->first(),
        ];
    }

    /** @param  array<string, mixed>  $overrides */
    private function bill(array $overrides = []): array
    {
        $m = $this->members;

        return $overrides + [
            'title' => 'Dinner at Kasundi', 'amount' => 977.50, 'category' => 'food', 'spent_on' => now()->toDateString(),
            'trip_member_id' => $m['leader']->id, 'split_type' => 'itemised',
            'items' => [
                ['name' => 'Biryani', 'price' => 300, 'members' => [$m['asha']->id]],
                ['name' => 'Kacchi', 'price' => 400, 'members' => [$m['bilal']->id, $m['leader']->id]],
                ['name' => 'Borhani', 'price' => 150, 'members' => [$m['asha']->id, $m['bilal']->id, $m['leader']->id]],
            ],
        ];
    }

    /** @return array<string, float> */
    private function shares(Expense $expense): array
    {
        $byId = $expense->shares()->pluck('amount', 'trip_member_id');

        return collect($this->members)->map(fn (TripMember $m) => (float) ($byId[$m->id] ?? 0))->all();
    }

    public function test_each_person_pays_for_what_they_had_plus_their_part_of_vat(): void
    {
        $this->actingAs($this->leader)->post(route('trips.expenses.store', $this->trip), $this->bill())->assertSessionHasNoErrors();

        $expense = Expense::firstOrFail();
        $this->assertSame(SplitType::Itemised, $expense->split_type);
        $this->assertCount(3, $expense->items);

        // 850 of food + 15% VAT = 977.50. Asha had 350, the others 250 each.
        $this->assertSame(['leader' => 287.5, 'asha' => 402.5, 'bilal' => 287.5], $this->shares($expense));

        $this->get(route('trips.show', $this->trip))->assertOk()->assertSee('By item')->assertSee('3 items on the bill')->assertSee('Biryani');
    }

    public function test_an_itemised_bill_needs_its_items_and_only_this_trips_travellers(): void
    {
        $this->actingAs($this->leader);

        $this->post(route('trips.expenses.store', $this->trip), $this->bill(['items' => []]))->assertSessionHasErrors('items');

        $stranger = TripMember::create(['trip_id' => Trip::factory()->create()->id, 'name' => 'Stranger', 'role' => 'member', 'status' => 'active']);
        $bill = $this->bill();
        $bill['items'][0]['members'] = [$stranger->id];
        $this->post(route('trips.expenses.store', $this->trip), $bill)->assertSessionHasErrors('items');

        $bill = $this->bill();
        $bill['items'][0]['members'] = [];
        $this->post(route('trips.expenses.store', $this->trip), $bill)->assertSessionHasErrors('items.0.members');

        $this->assertSame(0, Expense::count());
    }

    public function test_switching_an_itemised_bill_to_an_equal_split_drops_the_items(): void
    {
        $this->actingAs($this->leader)->post(route('trips.expenses.store', $this->trip), $this->bill());
        $expense = Expense::firstOrFail();

        $this->put(route('trips.expenses.update', [$this->trip, $expense]), $this->bill([
            'amount' => 900, 'split_type' => 'equal', 'items' => null,
            'participant_ids' => collect($this->members)->pluck('id')->all(),
        ]))->assertSessionHasNoErrors();

        $expense->refresh();
        $this->assertNull($expense->items);
        $this->assertSame(['leader' => 300.0, 'asha' => 300.0, 'bilal' => 300.0], $this->shares($expense));
    }

    public function test_a_receipt_photo_is_resized_cleaned_and_shown_to_the_trip_only(): void
    {
        $this->actingAs($this->leader)->post(route('trips.expenses.store', $this->trip), [
            'title' => 'Bus tickets', 'amount' => 1800, 'category' => 'transport', 'spent_on' => now()->toDateString(),
            'trip_member_id' => $this->members['leader']->id, 'split_type' => 'equal',
            'receipt' => UploadedFile::fake()->image('bill.jpg', 2400, 3200),
        ])->assertSessionHasNoErrors();

        $expense = Expense::firstOrFail();
        $this->assertStringStartsWith('receipts/'.$this->trip->id.'/'.$expense->id.'-', $expense->receipt_path);
        Storage::disk('local')->assertExists($expense->receipt_path);

        [$width, $height] = getimagesizefromstring(Storage::disk('local')->get($expense->receipt_path));
        $this->assertSame([1200, 1600], [$width, $height]);

        $this->actingAs($this->asha)->get($expense->receiptUrl())->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->actingAs(User::factory()->create())->get($expense->receiptUrl())->assertForbidden();

        $this->actingAs($this->asha)->get(route('trips.show', $this->trip))->assertSee('Receipt');
    }

    public function test_a_receipt_can_be_replaced_removed_and_goes_with_its_cost(): void
    {
        $this->actingAs($this->leader);
        $base = [
            'title' => 'Cottage', 'amount' => 3000, 'category' => 'accommodation', 'spent_on' => now()->toDateString(),
            'trip_member_id' => $this->members['leader']->id, 'split_type' => 'equal',
        ];

        $this->post(route('trips.expenses.store', $this->trip), $base + ['receipt' => UploadedFile::fake()->image('a.png', 400, 600)]);
        $expense = Expense::firstOrFail();
        $first = $expense->receipt_path;

        $this->put(route('trips.expenses.update', [$this->trip, $expense]), $base + ['receipt' => UploadedFile::fake()->image('b.jpg', 400, 600)]);
        $second = $expense->refresh()->receipt_path;
        $this->assertNotSame($first, $second);
        Storage::disk('local')->assertMissing($first);

        $this->put(route('trips.expenses.update', [$this->trip, $expense]), $base + ['remove_receipt' => '1']);
        $this->assertNull($expense->refresh()->receipt_path);
        Storage::disk('local')->assertMissing($second);

        $this->put(route('trips.expenses.update', [$this->trip, $expense]), $base + ['receipt' => UploadedFile::fake()->image('c.jpg', 400, 600)]);
        $third = $expense->refresh()->receipt_path;
        $this->delete(route('trips.expenses.destroy', [$this->trip, $expense]));
        Storage::disk('local')->assertMissing($third);
    }

    public function test_deleting_a_trip_deletes_its_receipts(): void
    {
        $this->actingAs($this->leader)->post(route('trips.expenses.store', $this->trip), [
            'title' => 'Boat', 'amount' => 600, 'category' => 'transport', 'spent_on' => now()->toDateString(),
            'trip_member_id' => $this->members['leader']->id, 'split_type' => 'equal',
            'receipt' => UploadedFile::fake()->image('boat.jpg', 300, 300),
        ]);
        $path = Expense::firstOrFail()->receipt_path;

        $this->delete(route('trips.destroy', $this->trip))->assertRedirect();
        Storage::disk('local')->assertMissing($path);
    }

    public function test_only_photos_are_accepted_and_a_bad_one_saves_nothing(): void
    {
        $this->actingAs($this->leader)->post(route('trips.expenses.store', $this->trip), [
            'title' => 'Boat', 'amount' => 600, 'category' => 'transport', 'spent_on' => now()->toDateString(),
            'trip_member_id' => $this->members['leader']->id, 'split_type' => 'equal',
            'receipt' => UploadedFile::fake()->create('bill.pdf', 20, 'application/pdf'),
        ])->assertSessionHasErrors('receipt');

        // Named like a photo, but not one.
        $this->post(route('trips.expenses.store', $this->trip), [
            'title' => 'Boat', 'amount' => 600, 'category' => 'transport', 'spent_on' => now()->toDateString(),
            'trip_member_id' => $this->members['leader']->id, 'split_type' => 'equal',
            'receipt' => UploadedFile::fake()->createWithContent('bill.jpg', 'not really a jpeg'),
        ])->assertSessionHasErrors('receipt');

        $this->assertSame(0, Expense::count());
    }
}
