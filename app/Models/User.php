<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property-read Customer|null $customer
 * @property-read Operator|null $operator
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class);
    }

    public function operator(): HasOne
    {
        return $this->hasOne(Operator::class);
    }

    /** Orders belonging to this user's customer profile. */
    public function orders(): HasManyThrough
    {
        return $this->hasManyThrough(Order::class, Customer::class, 'user_id', 'customer_id', 'id', 'id');
    }

    /** Productions assigned to this user's operator profile. */
    public function productions(): HasManyThrough
    {
        return $this->hasManyThrough(ProductionOrder::class, Operator::class, 'user_id', 'operator_id', 'id', 'id');
    }

    public function productionOrders(): HasManyThrough
    {
        return $this->productions();
    }

    public function isRole(UserRole|string $role): bool
    {
        $value = $this->role instanceof UserRole ? $this->role->value : (string) $this->role;

        return $value === ($role instanceof UserRole ? $role->value : $role);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, function (Builder $builder, string $value) {
            $like = '%'.trim($value).'%';
            $builder->where(function (Builder $nested) use ($like) {
                $nested->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like);
            });
        });
    }
}
