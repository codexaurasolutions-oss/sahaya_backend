<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Acl extends Model
{
    use HasFactory;
    public function get_admin_module_action(){
		return $this->hasMany('App\Models\AclAdminAction','admin_module_id','id');
	}
}
