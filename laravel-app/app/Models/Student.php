<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'full_name',
        'student_code',
        'account_number',
        'classroom',
        'password',
        'password_hash',
        'photo_filename',
        'default_password',
    ];

    protected $hidden = ['password_hash'];

    protected $casts = [
        'default_password' => 'boolean',
    ];

    public function setPasswordAttribute($value)
    {
        if ($value === null) {
            $this->attributes['password_hash'] = null;
            return;
        }

        // If value already looks like a bcrypt hash, store directly
        if (is_string($value) && preg_match('/^\$2y\$|^\$2a\$|^\$argon2/', $value)) {
            $this->attributes['password_hash'] = $value;
        } else {
            $this->attributes['password_hash'] = Hash::make($value);
        }
    }

    public function getPasswordAttribute()
    {
        return $this->attributes['password_hash'] ?? null;
    }
}
