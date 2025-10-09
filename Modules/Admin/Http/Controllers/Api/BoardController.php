<?php
// Modules/Admin/Http/Controllers/Api/BoardController.php

namespace Modules\Admin\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\Board;
use App\Models\Task;

class BoardController extends Controller
{
    /**
     * Get all boards (active only)
     */
    public function index(): JsonResponse
    {
        try {
            $boards = Board::active()
                ->ordered()
                ->withCount('tasks')
                ->get();

            $trashedCount = Board::onlyTrashed()->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'boards' => $boards,
                    'trashed_count' => $trashedCount,
                    'can_manage' => Auth::user()->hasRole('super')
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load boards',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get trashed (deleted) boards
     */
    public function trashed(): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user->hasRole('super')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only super admins can view deleted boards'
                ], 403);
            }

            $trashedBoards = Board::onlyTrashed()
                ->ordered()
                ->withCount('tasks')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'boards' => $trashedBoards
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load deleted boards',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Create a new board (super admin only)
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user->hasRole('super')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only super admins can create boards'
                ], 403);
            }

            $validator = Validator::make($request->all(), Board::getValidationRules());

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Get the next order value
            $maxOrder = Board::max('order') ?? 0;

            $board = Board::create([
                'name' => $request->name,
                'color' => $request->color,
                'order' => $request->order ?? ($maxOrder + 1),
                'is_default' => false,
                'created_by' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Board created successfully',
                'data' => ['board' => $board]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create board',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update a board (super admin only)
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user->hasRole('super')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only super admins can update boards'
                ], 403);
            }

            $board = Board::findOrFail($id);

            // Prevent updating default boards
            if ($board->is_default) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot update default boards'
                ], 422);
            }

            $validator = Validator::make($request->all(), Board::getValidationRules($board->id));

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $board->update($request->only(['name', 'color', 'order', 'is_active']));

            return response()->json([
                'success' => true,
                'message' => 'Board updated successfully',
                'data' => ['board' => $board->fresh()]
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Board not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update board',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Soft delete a board (super admin only)
     */
    public function destroy($id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user->hasRole('super')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only super admins can delete boards'
                ], 403);
            }

            $board = Board::findOrFail($id);

            // Prevent deleting default boards
            if ($board->is_default) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete default boards'
                ], 422);
            }

            // Check if board has tasks
            if (!$board->canDelete()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete board that contains tasks'
                ], 422);
            }

            $board->delete();

            return response()->json([
                'success' => true,
                'message' => 'Board moved to trash successfully'
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Board not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete board',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Restore a soft-deleted board (super admin only)
     */
    public function restore($id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user->hasRole('super')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only super admins can restore boards'
                ], 403);
            }

            $board = Board::onlyTrashed()->findOrFail($id);

            // Check if there's already an active board with the same name
            $existingBoard = Board::where('name', $board->name)->first();
            if ($existingBoard) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot restore board. An active board with the same name already exists.'
                ], 422);
            }

            $board->restore();

            return response()->json([
                'success' => true,
                'message' => 'Board restored successfully',
                'data' => ['board' => $board->fresh()]
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Deleted board not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to restore board',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Permanently delete a board (super admin only)
     */
    public function forceDelete($id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user->hasRole('super')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only super admins can permanently delete boards'
                ], 403);
            }

            $board = Board::onlyTrashed()->findOrFail($id);

            // Prevent force deleting default boards
            if ($board->is_default) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot permanently delete default boards'
                ], 422);
            }

            $board->forceDelete();

            return response()->json([
                'success' => true,
                'message' => 'Board permanently deleted'
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Deleted board not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to permanently delete board',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Reorder boards (super admin only)
     */
    public function reorder(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user->hasRole('super')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only super admins can reorder boards'
                ], 403);
            }

            $request->validate([
                'boards' => 'required|array',
                'boards.*.id' => 'required|exists:boards,id',
                'boards.*.order' => 'required|integer'
            ]);

            foreach ($request->boards as $boardData) {
                Board::where('id', $boardData['id'])->update(['order' => $boardData['order']]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Boards reordered successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reorder boards',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get board statistics
     */
    public function stats(): JsonResponse
    {
        try {
            $boards = Board::active()
                ->ordered()
                ->withCount('tasks')
                ->get();

            $totalTasks = Task::count();
            $completedTasks = Task::whereHas('board', function($query) {
                $query->where('name', 'completed');
            })->count();

            $trashedCount = Board::onlyTrashed()->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'boards' => $boards,
                    'stats' => [
                        'total_tasks' => $totalTasks,
                        'completed_tasks' => $completedTasks,
                        'completion_rate' => $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 2) : 0,
                        'trashed_boards' => $trashedCount
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load board statistics',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
