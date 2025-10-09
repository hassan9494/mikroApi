<?php
// app/Models/Task.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'status',
        'position',
        'due_date',
        'priority',
        'labels',
        'is_completed',
        'completed_at',
        'assignees',
        'comments',
        'is_public'
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'completed_at' => 'datetime',
        'labels' => 'array',
        'assignees' => 'array',
        'comments' => 'array',
        'priority' => 'integer',
        'is_public' => 'boolean',
        'status' => 'string'
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }


    // Scope to get tasks accessible by a user (created by or assigned to)
// In app/Models/Task.php - Fix the accessibleBy scope
    public function scopeAccessibleBy($query, $userId)
    {
        return $query->where(function($q) use ($userId) {
            $q->where('user_id', $userId)
                ->orWhereJsonContains('assignees', $userId)
                ->orWhere('is_public', true);
        });
    }

    // Check if user can modify this task (creator OR super admin)
    public function canModify($userId)
    {
        // Check if user is super admin
        $user = User::find($userId);
        if ($user && $user->hasRole('super')) {
            return true;
        }

        return $this->user_id == $userId;
    }

    // Check if user is a super admin
    public function isUserSuperAdmin($userId)
    {
        $user = User::find($userId);
        return $user && $user->hasRole('super');
    }

    // Get super admin users
    public function getSuperAdmins()
    {
        return User::whereHas('roles', function ($query) {
            $query->where('name', 'super');
        })->where('status', 1)
            ->get(['id', 'name', 'email'])
            ->pluck('id')
            ->toArray();
    }

    // Add assignee with permission check
    public function addAssignee($userId, $currentUserId)
    {
        $assignees = $this->assignees ?? [];
        if (!in_array($userId, $assignees)) {
            $assignees[] = $userId;
            $this->update(['assignees' => $assignees]);
        }
    }

    // Remove assignee with permission check
// In app/Models/Task.php - update removeAssignee method
    public function removeAssignee($userId, $currentUserId = null)
    {
        $assignees = $this->assignees ?? [];

        // Check if trying to remove a super admin (prevent removal)
        $superAdminIds = $this->getSuperAdmins();
        if (in_array($userId, $superAdminIds)) {
            return false; // Cannot remove super admins
        }

        $assignees = array_filter($assignees, function ($id) use ($userId) {
            return $id != $userId;
        });

        $this->update(['assignees' => array_values($assignees)]);
        return true;
    }

    // Scopes
    public function scopeTodo($query)
    {
        return $query->where('status', 'todo');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'inProgress');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    // Methods
    public function complete()
    {
        $this->update([
            'status' => 'completed',
            'is_completed' => true,
            'completed_at' => now()
        ]);
    }

    public function reopen()
    {
        $this->update([
            'status' => 'todo',
            'is_completed' => false,
            'completed_at' => null
        ]);
    }

    public function isCompleted()
    {
        return $this->status === 'completed' && $this->is_completed === true;
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($task) {
            // If board_id is not set but status is, try to find the board
            if (!$task->board_id && $task->status) {
                $board = Board::where('name', $task->status)->first();
                if ($board) {
                    $task->board_id = $board->id;
                }
            }

            // Set status from board if board_id is provided but status is not
            if ($task->board_id && !$task->status) {
                $board = Board::find($task->board_id);
                if ($board) {
                    $task->status = $board->name;
                }
            }
        });
        static::updating(function ($task) {
            // If board is changed, update status to match board name
            if ($task->isDirty('board_id') && $task->board_id) {
                $board = Board::find($task->board_id);
                if ($board) {
                    $task->status = $board->name;
                }
            }

            // If status is changed, try to find matching board
            if ($task->isDirty('status') && !$task->isDirty('board_id')) {
                $board = Board::where('name', $task->status)->first();
                if ($board) {
                    $task->board_id = $board->id;
                }
            }
        });
    }
    public function scopeByBoard($query, $boardId)
    {
        return $query->where('board_id', $boardId);
    }

// Add this method for backward compatibility
    public function scopeByStatus($query, $status)
    {
        // First try to find board with this name, then get tasks for that board
        $board = Board::where('name', $status)->first();
        if ($board) {
            return $query->where('board_id', $board->id);
        }

        // Fallback to status field for backward compatibility
        return $query->where('status', $status);
    }
    public function addComment($userId, $content)
    {
        $comments = $this->comments ?? [];
        $comments[] = [
            'id' => uniqid(),
            'user_id' => $userId,
            'content' => $content,
            'created_at' => now()->toISOString(),
            'user_name' => User::find($userId)->name ?? 'Unknown User'
        ];

        $this->update(['comments' => $comments]);
    }


    // Make task public - automatically assign all available users except creator
    public function makePublic($availableUserIds, $creatorId = null)
    {
        $creatorId = $creatorId ?? $this->user_id;

        // Filter out creator from assignees
        $assignees = array_filter($availableUserIds, function ($userId) use ($creatorId) {
            return $userId != $creatorId;
        });

        $this->update([
            'is_public' => true,
            'assignees' => array_values($assignees)
        ]);
    }

    // Make task private - keep existing assignees or clear them
    public function makePrivate($keepAssignees = false)
    {
        $updateData = ['is_public' => false];

        if (!$keepAssignees) {
            $updateData['assignees'] = [];
        }

        $this->update($updateData);
    }

    // Check if user can access this task
    public function canAccess($userId)
    {
        return $this->user_id == $userId ||
            in_array($userId, $this->assignees ?? []) ||
            $this->is_public;
    }

    public function updateComment($commentId, $userId, $newContent)
    {
        $comments = $this->comments ?? [];

        foreach ($comments as &$comment) {
            if ($comment['id'] === $commentId && $comment['user_id'] == $userId) {
                $comment['content'] = $newContent;
                $comment['updated_at'] = now()->toISOString();
                $comment['edited'] = true;

                $this->update(['comments' => $comments]);
                return true;
            }
        }

        return false;
    }

// Add this method to delete comments
// app/Models/Task.php (Update deleteComment method)
    public function deleteComment($commentId, $userId)
    {
        $comments = $this->comments ?? [];
        $originalCount = count($comments);

        $comments = array_filter($comments, function ($comment) use ($commentId, $userId) {
            // Allow deletion if user owns the comment OR is super admin
            if ($comment['id'] === $commentId) {
                // Check if user is super admin
                $user = User::find($userId);
                if ($user && $user->hasRole('super')) {
                    return false; // Remove the comment (allow deletion)
                }
                // For non-super admins, only allow if they own the comment
                return $comment['user_id'] != $userId;
            }
            return true;
        });

        if (count($comments) < $originalCount) {
            $this->update(['comments' => array_values($comments)]);
            return true;
        }

        return false;
    }

// Attachments relationship
    public function attachments()
    {
        return $this->hasMany(TaskAttachment::class);
    }
// app/Models/Task.php

// Add method to get employee users
    public function getEmployeeUsers()
    {
        $employeeRoles = ['admin', 'Admin cash', 'Manager', 'Cashier', 'Product Manager', 'Distributer', 'Acountant'];

        return User::where('status', 1)
            ->whereHas('roles', function ($query) use ($employeeRoles) {
                $query->whereIn('name', $employeeRoles);
            })
            ->with(['roles' => function($query) {
                $query->select('name');
            }])
            ->get()
            ->map(function($user) {
                $user->roles = $user->roles->pluck('name')->toArray();
                return $user;
            });
    }
    public function board()
    {
        return $this->belongsTo(Board::class);
    }

// Update assignedUsers method to only return employees
    public function assignedUsers()
    {
        $employeeRoles = ['admin', 'Admin cash', 'Manager', 'Cashier', 'Product Manager', 'Distributer', 'Acountant'];

        return User::whereIn('id', $this->assignees ?? [])
            ->where('status', 1)
            ->whereHas('roles', function ($query) use ($employeeRoles) {
                $query->whereIn('name', $employeeRoles);
            })
            ->with(['roles' => function($query) {
                $query->select('name');
            }])
            ->get()
            ->map(function($user) {
                $user->roles = $user->roles->pluck('name')->toArray();
                return $user;
            });
    }
    protected $withCount = ['attachments'];

}
