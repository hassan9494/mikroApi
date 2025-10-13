<?php
// Modules/Admin/Http/Controllers/Api/TaskAttachmentController.php

namespace Modules\Admin\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use App\Models\Task;
use App\Models\TaskAttachment;

class TaskAttachmentController extends Controller
{
    /**
     * Get attachments for a task
     */
    public function index($taskId): JsonResponse
    {
        try {
            $user = Auth::user();

            // Find task - super admins can access any task, others use accessibleBy scope
            if ($user->hasRole('super')) {
                $task = Task::findOrFail($taskId);
            } else {
                $task = Task::accessibleBy($user->id)->findOrFail($taskId);
            }

            $attachments = $task->attachments()
                ->with('user:id,name,email')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'attachments' => $attachments
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
                'message' => 'Failed to load attachments',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Upload attachment for a task
     */
    public function store(Request $request, $taskId): JsonResponse
    {
        try {
            $request->validate([
                'file' => 'required|file|max:102400', // 100MB max
            ]);

            $user = Auth::user();

            // Find task - super admins can access any task, others use accessibleBy scope
            if ($user->hasRole('super')) {
                $task = Task::findOrFail($taskId);
            } else {
                $task = Task::accessibleBy($user->id)->findOrFail($taskId);
            }

            $file = $request->file('file');
            $originalName = $file->getClientOriginalName();
            $mimeType = $file->getMimeType();
            $fileSize = $file->getSize();

            // Generate unique filename
            $filename = uniqid() . '_' . time() . '.' . $file->getClientOriginalExtension();

            // Store file
            $path = $file->storeAs('task-attachments', $filename, 'public');

            // Create attachment record
            $attachment = TaskAttachment::create([
                'task_id' => $task->id,
                'user_id' => $user->id,
                'filename' => $filename,
                'original_name' => $originalName,
                'mime_type' => $mimeType,
                'path' => $path,
                'size' => $fileSize,
            ]);

            $attachment->load('user');

            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully',
                'data' => ['attachment' => $attachment]
            ], 201);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Task not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload file',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Delete an attachment
     */
    public function destroy($taskId, $attachmentId): JsonResponse
    {
        try {
            $user = Auth::user();

            // Find task - super admins can access any task, others use accessibleBy scope
            if ($user->hasRole('super')) {
                $task = Task::findOrFail($taskId);
            } else {
                $task = Task::accessibleBy($user->id)->findOrFail($taskId);
            }

            $attachment = $task->attachments()->findOrFail($attachmentId);

            // Check if user owns the attachment or is super admin
            if ($attachment->user_id !== $user->id && !$user->hasRole('super')) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to delete this file'
                ], 403);
            }

            // Delete file from storage
            Storage::disk('public')->delete($attachment->path);

            // Delete attachment record
            $attachment->delete();

            return response()->json([
                'success' => true,
                'message' => 'File deleted successfully'
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Attachment not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete file',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Download an attachment
     */
    public function download($taskId, $attachmentId)
    {
        try {
            $user = Auth::user();

            // Find task - super admins can access any task, others use accessibleBy scope
            if ($user->hasRole('super')) {
                $task = Task::findOrFail($taskId);
            } else {
                $task = Task::accessibleBy($user->id)->findOrFail($taskId);
            }

            $attachment = $task->attachments()->findOrFail($attachmentId);

            $filePath = Storage::disk('public')->path($attachment->path);

            return response()->download($filePath, $attachment->original_name);

        } catch (\Exception $e) {
            abort(404, 'File not found');
        }
    }
}
