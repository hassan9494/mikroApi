<?php
// Modules/Admin/Http/Controllers/Api/TaskController.php

namespace Modules\Admin\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\Task;
use App\Models\User;
use App\Models\Board;
use Carbon\Carbon;

class TaskController extends Controller
{

// Add this method to get employee roles
    private function getEmployeeRoles()
    {
        return [
            'super',
            'admin',
            'Admin cash',
            'Manager',
            'Cashier',
            'Product Manager',
            'Distributer',
            'Acountant'
        ];
    }

// Update the getAvailableUsers method
    public function getAvailableUsers(): JsonResponse
    {
        try {
            $employeeRoles = $this->getEmployeeRoles();

            // Get users who are employees but NOT super admins
            $users = User::where('status', 1)
                ->whereHas('roles', function ($query) use ($employeeRoles) {
                    $query->whereIn('name', $employeeRoles);
                })
                ->whereDoesntHave('roles', function ($query) {
                    $query->where('name', 'super'); // Exclude super admins
                })
                ->with(['roles' => function($query) {
                    $query->select('name');
                }])
                ->get(['id', 'name', 'email'])
                ->map(function($user) {
                    $user->roles = $user->roles->pluck('name')->toArray();
                    return $user;
                });

            // Get super admins separately for display purposes
            $superAdmins = User::where('status', 1)
                ->whereHas('roles', function ($query) {
                    $query->where('name', 'super');
                })
                ->with(['roles' => function($query) {
                    $query->select('name');
                }])
                ->get(['id', 'name', 'email'])
                ->map(function($user) {
                    $user->roles = $user->roles->pluck('name')->toArray();
                    return $user;
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'users' => $users,
                    'super_admins' => $superAdmins, // For display only, not for selection
                    'employee_roles' => $employeeRoles
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load users',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'board_id' => 'required|exists:boards,id',
                'status' => 'sometimes|string|max:255',
                'assignees' => 'sometimes|array',
                'assignees.*' => 'exists:users,id',
                'is_public' => 'sometimes|boolean'
            ]);

            $user = Auth::user();

            // Get the board
            $board = Board::active()->findOrFail($request->board_id);

            // Get the next position for the new task
            $lastPosition = Task::where('board_id', $request->board_id)
                    ->max('position') ?? 0;

            $taskData = [
                'user_id' => $user->id,
                'board_id' => $board->id,
                'title' => $request->title,
                'description' => $request->description,
                'status' => $board->name,
                'position' => $lastPosition + 1,
                'due_date' => $request->due_date,
                'priority' => $request->priority ?? 0,
                'labels' => $request->labels ? json_decode($request->labels) : null,
                'is_public' => $request->is_public ?? false,
                'assignees' => $request->assignees ?? []
            ];

            \Log::info('Creating task with data:', $taskData);

            $task = Task::create($taskData);
            $task->load('user', 'board');

            return response()->json([
                'success' => true,
                'message' => 'Task created successfully',
                'data' => ['task' => $task]
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Task creation failed:', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create task',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

// Update addAssignee method to validate employee role
    public function addAssignee(Request $request, $id): JsonResponse
    {
        try {
            $request->validate([
                'user_id' => 'required|exists:users,id'
            ]);

            $user = Auth::user();
            $employeeRoles = $this->getEmployeeRoles();

            // Check if the user to be assigned is an employee
            $assigneeUser = User::where('id', $request->user_id)
                ->where('status', 1)
                ->whereHas('roles', function ($query) use ($employeeRoles) {
                    $query->whereIn('name', $employeeRoles);
                })
                ->first();

            if (!$assigneeUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'The selected user is not an employee and cannot be assigned to tasks'
                ], 422);
            }

            // Find task
            if ($user->hasRole('super')) {
                $task = Task::findOrFail($id);
            } else {
                $task = Task::where('user_id', $user->id)->findOrFail($id);
            }

            // Check if user can modify this task (creator or super admin)
            if (!$task->canModify($user->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to add assignees to this task'
                ], 403);
            }

            $task->addAssignee($request->user_id, $user->id);
            $task->load('user');

            $assignee = User::find($request->user_id);

            return response()->json([
                'success' => true,
                'message' => 'Assignee added successfully',
                'data' => [
                    'task' => $task->fresh(),
                    'assignee' => $assignee
                ]
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Task not found or you do not have permission to modify it'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add assignee',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
    /**
     * Get users with super admin role
     */
    private function getSuperAdmins()
    {
        return User::whereHas('roles', function ($query) {
            $query->where('name', 'super');
        })->where('status', 1)
            ->get(['id', 'name', 'email'])
            ->pluck('id')
            ->toArray();
    }

    /**
     * Get all users (for assignee dropdown)
     */
    private function getAllUsers()
    {
        return User::where('status', 1)
            ->with(['roles' => function($query) {
                $query->select('name');
            }])
            ->get(['id', 'name', 'email'])
            ->map(function($user) {
                $user->roles = $user->roles->pluck('name')->toArray();
                return $user;
            });
    }

    /**
     * Remove assignee from task - Only creator and super admins can remove assignees
     */
    public function removeAssignee(Request $request, $id): JsonResponse
    {
        try {
            $request->validate([
                'user_id' => 'required|exists:users,id'
            ]);

            $user = Auth::user();

            // Find task
            if ($user->hasRole('super')) {
                $task = Task::findOrFail($id);
            } else {
                $task = Task::where('user_id', $user->id)->findOrFail($id);
            }

            // Check if user can modify this task (creator or super admin)
            if (!$task->canModify($user->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to remove assignees from this task'
                ], 403);
            }

            // Prevent removal of super admins by non-super admins
            $superAdminIds = $this->getSuperAdmins();
            if (in_array($request->user_id, $superAdminIds) && !$user->hasRole('super')) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot remove super admins from tasks'
                ], 403);
            }

            $task->removeAssignee($request->user_id);
            $task->load('user');

            return response()->json([
                'success' => true,
                'message' => 'Assignee removed successfully',
                'data' => ['task' => $task->fresh()]
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Task not found or you do not have permission to modify it'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove assignee',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function getBoardData(): JsonResponse
    {
        try {
            $userId = Auth::id();
            $user = Auth::user();

            // Get all active boards
            $boards = Board::active()->ordered()->get();

            $boardData = [];

            foreach ($boards as $board) {
                // Use the new byBoard scope instead of status
                if ($user->hasRole('super')) {
                    $tasks = Task::where('board_id', $board->id)
                        ->withCount('attachments')
                        ->with('user')
                        ->orderBy('position')
                        ->get();
                } else {
                    $tasks = Task::where('board_id', $board->id)
                        ->where(function($query) use ($userId) {
                            $query->where('user_id', $userId)
                                ->orWhereJsonContains('assignees', $userId)
                                ->orWhere('is_public', true);
                        })
                        ->withCount('attachments')
                        ->with('user')
                        ->orderBy('position')
                        ->get();
                }

                // Return tasks as array directly for the board
                $boardData[$board->id] = $tasks;
            }

            return response()->json([
                'success' => true,
                'data' => $boardData
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to load board data:', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load board data',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();

            // Super admins can access any task, others use accessibleBy scope
            if ($user->hasRole('super')) {
                $task = Task::findOrFail($id);
            } else {
                $task = Task::accessibleBy($user->id)->findOrFail($id);
            }

            // FIXED: Remove the enum validation for status
            $request->validate([
                'title' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'status' => 'sometimes|string|max:255', // ← Changed to accept any string
                'is_public' => 'sometimes|boolean',
                'board_id' => 'sometimes|exists:boards,id' // Add board_id validation
            ]);

            $updateData = $request->only(['title', 'description', 'status', 'due_date', 'priority', 'is_public', 'board_id']);

            if ($request->has('labels')) {
                $updateData['labels'] = json_decode($request->labels);
            }

            // Get super admin IDs
            $superAdminIds = $this->getSuperAdmins();

            // Handle public/private changes and super admins
            if ($request->has('is_public')) {
                if ($request->is_public && !$task->is_public) {
                    // Making task public - assign all users except creator, plus super admins
                    $allUserIds = User::where('status', 1)
                        ->where('id', '!=', $task->user_id)
                        ->pluck('id')
                        ->toArray();
                    $updateData['assignees'] = array_values(array_unique(array_merge($superAdminIds, $allUserIds)));
                } elseif (!$request->is_public && $task->is_public) {
                    // Making task private - keep only super admins and any existing non-public assignees
                    $currentAssignees = $task->assignees ?? [];
                    $nonSuperAdminAssignees = array_filter($currentAssignees, function ($userId) use ($superAdminIds) {
                        return !in_array($userId, $superAdminIds);
                    });
                    $updateData['assignees'] = array_values(array_unique(array_merge($superAdminIds, $nonSuperAdminAssignees)));
                }
            } else {
                // For regular updates, ensure super admins are always included
                $currentAssignees = $task->assignees ?? [];
                $updateData['assignees'] = array_values(array_unique(array_merge($superAdminIds, $currentAssignees)));
            }

            $task->update($updateData);
            $task->load('user');

            return response()->json([
                'success' => true,
                'message' => 'Task updated successfully',
                'data' => ['task' => $task->fresh()]
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Task not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update task',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Show the specified task - super admins can access any task
     */
    public function show($id): JsonResponse
    {
        try {
            $user = Auth::user();

            // Super admins can access any task, others use accessibleBy scope
            if ($user->hasRole('super')) {
                $task = Task::with('user')->findOrFail($id);
            } else {
                $task = Task::accessibleBy($user->id)->with('user')->findOrFail($id);
            }

            // Load assignee details
            $assignees = User::whereIn('id', $task->assignees ?? [])->get(['id', 'name', 'email']);

            // Get available users (excluding creator)
            $availableUsers = User::where('status', 1)
                ->where('id', '!=', $task->user_id)
                ->get(['id', 'name', 'email']);

            return response()->json([
                'success' => true,
                'data' => [
                    'task' => $task,
                    'assignees' => $assignees,
                    'available_users' => $availableUsers,
                    'can_edit' => $task->user_id == $user->id || $user->hasRole('super')
                ]
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Task not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load task',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Remove the specified task - super admins can delete any task
     */
    public function destroy($id): JsonResponse
    {
        try {
            $user = Auth::user();

            // Super admins can delete any task, others can only delete their own
            if ($user->hasRole('super')) {
                $task = Task::findOrFail($id);
            } else {
                $task = Task::where('user_id', $user->id)->findOrFail($id);
            }

            $task->delete();

            return response()->json([
                'success' => true,
                'message' => 'Task deleted successfully'
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Task not found or you do not have permission to delete it'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete task',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Add comment to task - super admins can comment on any task
     */
    public function addComment(Request $request, $id): JsonResponse
    {
        try {
            $request->validate([
                'content' => 'required|string|max:1000'
            ]);

            $user = Auth::user();

            // Super admins can comment on any task, others use accessibleBy scope
            if ($user->hasRole('super')) {
                $task = Task::findOrFail($id);
            } else {
                $task = Task::accessibleBy($user->id)->findOrFail($id);
            }

            $task->addComment($user->id, $request->content);
            $task->load('user');

            return response()->json([
                'success' => true,
                'message' => 'Comment added successfully',
                'data' => ['task' => $task->fresh()]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add comment',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Toggle task public/private status - super admins can modify any task
     */
    public function togglePublic(Request $request, $id): JsonResponse
    {
        try {
            $request->validate([
                'is_public' => 'required|boolean'
            ]);

            $user = Auth::user();

            // Super admins can modify any task, others can only modify their own
            if ($user->hasRole('super')) {
                $task = Task::findOrFail($id);
            } else {
                $task = Task::where('user_id', $user->id)->findOrFail($id);
            }

            if ($request->is_public) {
                // Get all users except creator
                $allUserIds = User::where('status', 1)
                    ->where('id', '!=', $task->user_id)
                    ->pluck('id')
                    ->toArray();
                $task->makePublic($allUserIds, $user->id);
            } else {
                $task->makePrivate(false);
            }

            $task->load('user');

            return response()->json([
                'success' => true,
                'message' => $request->is_public ? 'Task is now public' : 'Task is now private',
                'data' => ['task' => $task->fresh()]
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Task not found or you do not have permission to modify it'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update task visibility',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Move task between columns with position - super admins can move any task
     */
    public function moveTask(Request $request, $id): JsonResponse
    {
        try {
            $request->validate([
                'status' => 'required|string|max:255',
                'position' => 'required|integer|min:0'
            ]);

            $user = Auth::user();

            // Super admins can move any task, others use accessibleBy scope
            if ($user->hasRole('super')) {
                $task = Task::findOrFail($id);
            } else {
                $task = Task::accessibleBy($user->id)->findOrFail($id);
            }

            DB::transaction(function () use ($task, $request, $user) {
                // If moving to a new status, update positions in target column
                if ($task->status !== $request->status) {
                    // Super admins update all tasks, others only their accessible tasks
                    if ($user->hasRole('super')) {
                        Task::where('status', $request->status)
                            ->where('position', '>=', $request->position)
                            ->increment('position');
                    } else {
                        Task::accessibleBy($user->id)
                            ->where('status', $request->status)
                            ->where('position', '>=', $request->position)
                            ->increment('position');
                    }
                }

                $task->update([
                    'status' => $request->status,
                    'position' => $request->position
                ]);
            });

            $task->load('user');

            return response()->json([
                'success' => true,
                'message' => 'Task moved successfully',
                'data' => ['task' => $task->fresh()]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to move task',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Complete a task - super admins can complete any task
     */
    public function completeTask($id): JsonResponse
    {
        try {
            $user = Auth::user();

            // Super admins can complete any task, others use accessibleBy scope
            if ($user->hasRole('super')) {
                $task = Task::findOrFail($id);
            } else {
                $task = Task::accessibleBy($user->id)->findOrFail($id);
            }

            $task->complete();
            $task->load('user');

            return response()->json([
                'success' => true,
                'message' => 'Task completed successfully',
                'data' => ['task' => $task->fresh()]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete task',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function getCompletedTasksGroupedByWeek(): JsonResponse
    {
        try {
            $user = Auth::user();

            // Get completed tasks - super admins see all, others see accessible tasks
            if ($user->hasRole('super')) {
                $completedTasks = Task::where('status', 'completed')
                    ->where('is_completed', true)
                    ->whereNotNull('completed_at')
                    ->with('user')
                    ->withCount('attachments') // ADD THIS LINE
                    ->orderBy('completed_at', 'desc')
                    ->get();
            } else {
                $completedTasks = Task::accessibleBy($user->id)
                    ->where('status', 'completed')
                    ->where('is_completed', true)
                    ->whereNotNull('completed_at')
                    ->with('user')
                    ->withCount('attachments') // ADD THIS LINE
                    ->orderBy('completed_at', 'desc')
                    ->get();
            }

            // Group tasks by week
            $groupedTasks = $completedTasks->groupBy(function ($task) {
                return Carbon::parse($task->completed_at)->startOfWeek()->format('Y-m-d');
            })->map(function ($weekTasks, $weekStart) {
                $startDate = Carbon::parse($weekStart);
                $endDate = $startDate->copy()->endOfWeek();

                return [
                    'week_start' => $startDate->format('Y-m-d'),
                    'week_end' => $endDate->format('Y-m-d'),
                    'week_label' => $this->getWeekLabel($startDate, $endDate),
                    'tasks' => $weekTasks,
                    'task_count' => $weekTasks->count()
                ];
            })->sortByDesc('week_start')->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'weeks' => $groupedTasks,
                    'total_completed_tasks' => $completedTasks->count()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load completed tasks',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    private function getWeekLabel($startDate, $endDate)
    {
        $start = $startDate->format('M j');
        $end = $endDate->format('M j, Y');

        // If same year, don't repeat year
        if ($startDate->year === $endDate->year) {
            $end = $endDate->format('M j');
        }

        // Check if it's current week
        $now = Carbon::now();
        if ($startDate->isSameWeek($now)) {
            return "This Week ({$start} - {$end})";
        }

        // Check if it's last week
        if ($startDate->isSameWeek($now->subWeek())) {
            return "Last Week ({$start} - {$end})";
        }

        return "Week of {$start} - {$end}";
    }
    /**
     * Reopen a task - super admins can reopen any task
     */
    public function reopenTask($id): JsonResponse
    {
        try {
            $user = Auth::user();

            // Super admins can reopen any task, others use accessibleBy scope
            if ($user->hasRole('super')) {
                $task = Task::findOrFail($id);
            } else {
                $task = Task::accessibleBy($user->id)->findOrFail($id);
            }

            $task->reopen();
            $task->load('user');

            return response()->json([
                'success' => true,
                'message' => 'Task reopened successfully',
                'data' => ['task' => $task->fresh()]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reopen task',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Reorder tasks within a column - super admins can reorder all tasks
     */
    public function reorderTasks(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'tasks' => 'required|array',
                'tasks.*.id' => 'required|exists:tasks,id',
                'tasks.*.position' => 'required|integer'
            ]);

            $user = Auth::user();

            DB::transaction(function () use ($request, $user) {
                foreach ($request->tasks as $taskData) {
                    // Super admins can update any task, others can only update their accessible tasks
                    if ($user->hasRole('super')) {
                        Task::where('id', $taskData['id'])
                            ->update(['position' => $taskData['position']]);
                    } else {
                        Task::accessibleBy($user->id)
                            ->where('id', $taskData['id'])
                            ->update(['position' => $taskData['position']]);
                    }
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Tasks reordered successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reorder tasks',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get deleted tasks - only for super admins
     */
    public function getDeletedTasks(): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user->hasRole('super')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only super admins can view deleted tasks.'
                ], 403);
            }

            $deletedTasks = Task::onlyTrashed()
                ->with(['user' => function($query) {
                    $query->select('id', 'name', 'email');
                }])
                ->orderBy('deleted_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'tasks' => $deletedTasks
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load deleted tasks',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Restore a deleted task - only for super admins
     */
    public function restoreTask($id): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user->hasRole('super')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only super admins can restore deleted tasks.'
                ], 403);
            }

            $task = Task::onlyTrashed()->findOrFail($id);
            $task->restore();

            $task->load('user');

            return response()->json([
                'success' => true,
                'message' => 'Task restored successfully',
                'data' => ['task' => $task]
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Deleted task not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to restore task',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Permanently delete a task - only for super admins
     */
    public function forceDeleteTask($id): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user->hasRole('super')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only super admins can permanently delete tasks.'
                ], 403);
            }

            $task = Task::onlyTrashed()->findOrFail($id);
            $task->forceDelete();

            return response()->json([
                'success' => true,
                'message' => 'Task permanently deleted'
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Deleted task not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to permanently delete task',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }


    public function updateComment(Request $request, $taskId, $commentId): JsonResponse
    {
        try {
            $request->validate([
                'content' => 'required|string|max:1000'
            ]);

            $user = Auth::user();

            // Find the task - super admins can access any task, others use accessibleBy scope
            if ($user->hasRole('super')) {
                $task = Task::findOrFail($taskId);
            } else {
                $task = Task::accessibleBy($user->id)->findOrFail($taskId);
            }

            // Update the comment - users can only update their own comments
            $updated = $task->updateComment($commentId, $user->id, $request->content);

            if (!$updated) {
                return response()->json([
                    'success' => false,
                    'message' => 'Comment not found or you do not have permission to edit it'
                ], 403);
            }

            $task->load('user');

            return response()->json([
                'success' => true,
                'message' => 'Comment updated successfully',
                'data' => ['task' => $task->fresh()]
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Task not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update comment',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Delete a comment - users can only delete their own comments
     */
// Modules/Admin/Http/Controllers/Api/TaskController.php (Update deleteComment method)
    public function deleteComment(Request $request, $taskId, $commentId): JsonResponse
    {
        try {
            $user = Auth::user();
            \Log::info('Delete comment attempt', [
                'user_id' => $user->id,
                'task_id' => $taskId,
                'comment_id' => $commentId
            ]);

            // Find the task - super admins can access any task, others use accessibleBy scope
            if ($user->hasRole('super')) {
                $task = Task::findOrFail($taskId);
            } else {
                $task = Task::accessibleBy($user->id)->findOrFail($taskId);
            }

            \Log::info('Task found', ['task_id' => $task->id, 'user_id' => $task->user_id]);

            // Delete the comment - users can only delete their own comments
            $deleted = $task->deleteComment($commentId, $user->id);

            \Log::info('Delete result', ['deleted' => $deleted]);

            if (!$deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Comment not found or you do not have permission to delete it'
                ], 403);
            }

            $task->load('user');

            return response()->json([
                'success' => true,
                'message' => 'Comment deleted successfully',
                'data' => ['task' => $task->fresh()]
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            \Log::error('Task not found', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Task not found'
            ], 404);
        } catch (\Exception $e) {
            \Log::error('Failed to delete comment', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete comment',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
