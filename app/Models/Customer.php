<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
    ];

    /**
     * Single definition of "the same email address" (case/whitespace-insensitive),
     * shared by validation, lookups and persistence.
     */
    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /**
     * Whatever path writes an email (service, factory, seeder, tinker) ends up normalized.
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => static::normalizeEmail($value),
        );
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
