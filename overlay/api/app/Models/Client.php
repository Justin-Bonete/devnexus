<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use BelongsToOrganization, HasUuids;

    protected $fillable = ['name', 'contact_json', 'status'];

    protected function casts(): array
    {
        return ['contact_json' => 'array'];
    }
}
