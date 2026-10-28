<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One traveller's checkpoint details on one trip. The whole record is
 * encrypted at rest; it is deleted a couple of weeks after the trip ends.
 */
class TravellerDetail extends Model
{
    public const ID_TYPES = [
        'nid' => 'National ID',
        'passport' => 'Passport',
        'birth' => 'Birth certificate',
        'student' => 'Student ID',
    ];

    public const BLOOD_GROUPS = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

    public const GENDERS = ['male' => 'Male', 'female' => 'Female', 'other' => 'Other'];

    /** The fields a traveller fills in, in form order. */
    public const FIELDS = [
        'full_name', 'id_type', 'id_number', 'date_of_birth', 'gender', 'phone',
        'address', 'blood_group', 'emergency_name', 'emergency_phone',
    ];

    protected $fillable = ['trip_member_id', 'submitted_by', 'data'];

    protected function casts(): array
    {
        return [
            'data' => 'encrypted:array',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(TripMember::class, 'trip_member_id');
    }

    public function value(string $field): ?string
    {
        $value = $this->data[$field] ?? null;

        return filled($value) ? (string) $value : null;
    }

    public function age(): ?int
    {
        $born = $this->value('date_of_birth');

        return $born ? Carbon::parse($born)->age : null;
    }

    /** "NID ••••5678" for screens; the full number only goes on the leader's PDF. */
    public function maskedId(): ?string
    {
        $number = $this->value('id_number');

        if ($number === null) {
            return null;
        }

        $type = __(['nid' => 'NID', 'passport' => 'Passport', 'birth' => 'Birth cert.', 'student' => 'Student ID'][$this->value('id_type')] ?? 'ID');

        return $type.' ••••'.mb_substr($number, -4);
    }

    public function idLabel(): string
    {
        return __(self::ID_TYPES[$this->value('id_type')] ?? 'ID');
    }
}
