<?php

namespace App\Sportsbook\Support;

use App\Models\User;

/**
 * A staff member acting from GMS, the back office (plan §5.5): GMS staff are not users of this site, so trading and
 * settlement actions accept either a local User or this. It is written to the activity log (`staff`) and, where a
 * record keeps who changed it, to a `*_by_staff` column.
 */
final readonly class StaffActor
{
    public function __construct(public string $id, public string $name, public ?string $email = null) {}

    /** "Jane Doe (GMS #12)": how the record and the log name them. */
    public function label(): string
    {
        return "{$this->name} (GMS #{$this->id})";
    }

    /** The activity log causer: a local user, or none for GMS staff (named in the properties instead). */
    public static function causer(User|self|null $by): ?User
    {
        return $by instanceof User ? $by : null;
    }

    /**
     * Activity log properties, with the GMS staff member added when one acted.
     *
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    public static function with(User|self|null $by, array $properties): array
    {
        return $by instanceof self
            ? $properties + ['staff' => ['source' => 'gms', 'id' => $by->id, 'name' => $by->name, 'email' => $by->email]]
            : $properties;
    }

    /** For a `*_by_staff` column. */
    public static function labelOf(User|self|null $by): ?string
    {
        return $by instanceof self ? $by->label() : null;
    }
}
