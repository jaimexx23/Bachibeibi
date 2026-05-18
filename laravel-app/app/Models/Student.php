<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;

    protected $fillable = ['full_name', 'student_code', 'account_number', 'classroom', 'password'];
    protected $hidden = ['password'];
}
