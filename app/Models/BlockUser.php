<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class BlockUser extends Model
{
    protected $table = 'block_users';

    public function userDetails()
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }

    public function blockUserDetails()
    {
        return $this->hasOne(User::class, 'id', 'block_user_id');
    }
}
