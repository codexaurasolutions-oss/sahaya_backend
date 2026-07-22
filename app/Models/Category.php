<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    // use SoftDeletes;

    protected $table = 'categories';

    protected $fillable = [
        'name',
        'image',
        'is_active',
        'is_deleted',
        'parent_id',
    ];

    /**
     * Relation: Category belongs to a User
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getImageAttribute($value)
    {
        if ($value) {
            return $value;
        }
        return null;
    }

    /**
     * Relation: Category may have a Parent Category
     */
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Relation: Category may have Many Children Categories
     */
    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }
}
