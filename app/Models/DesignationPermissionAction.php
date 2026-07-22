<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DesignationPermissionAction extends Model
{   
    protected $fillable = [
        'designation_id','designation_permission_id','admin_module_id','admin_sub_module_id','admin_module_action_id','is_active'
    ];
    use HasFactory;
}
