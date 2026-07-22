<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Follow extends Model
{
    use HasFactory;

    protected $table = 'followers';

    public function user()
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }

    public function userfollowing()
    {
        return $this->hasOne(User::class, 'id', 'member_user_id');
    }

}
