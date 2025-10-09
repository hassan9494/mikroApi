<?php
// app/Models/Board.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\Rule;

class Board extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'color',
        'order',
        'is_default',
        'is_active',
        'created_by'
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'order' => 'integer'
    ];

    // Add validation rules method
    public static function getValidationRules($boardId = null)
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('boards')->where(function ($query) {
                    return $query->whereNull('deleted_at');
                })->ignore($boardId)
            ],
            'color' => 'required|string|max:7',
            'order' => 'sometimes|integer',
            'is_active' => 'sometimes|boolean'
        ];
    }

    // Relationships
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeCustom($query)
    {
        return $query->where('is_default', false);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order')->orderBy('name');
    }

    public function scopeWithTrashed($query)
    {
        return $query->withTrashed();
    }

    public function scopeOnlyTrashed($query)
    {
        return $query->onlyTrashed();
    }

    // Methods
    public function canDelete()
    {
        return !$this->is_default && $this->tasks()->count() === 0;
    }

    public function canForceDelete()
    {
        return !$this->is_default;
    }

    public function getTaskCountAttribute()
    {
        return $this->tasks()->count();
    }

    public static function getDefaultBoards()
    {
        return [
            [
                'name' => 'todo',
                'color' => '#6c757d',
                'order' => 1,
                'is_default' => true
            ],
            [
                'name' => 'inProgress',
                'color' => '#ffc107',
                'order' => 2,
                'is_default' => true
            ],
            [
                'name' => 'completed',
                'color' => '#28a745',
                'order' => 3,
                'is_default' => true
            ]
        ];
    }
}
