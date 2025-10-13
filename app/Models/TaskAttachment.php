<?php
// app/Models/TaskAttachment.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'user_id',
        'filename',
        'original_name',
        'mime_type',
        'path',
        'size',
        'disk'
    ];

    protected $appends = [
        'file_url',
        'file_icon',
        'formatted_size'
    ];

    // Relationships
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Accessors
    public function getFileUrlAttribute(): string
    {
        return asset("storage/{$this->path}");
    }

    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->size;
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }

    public function getFileIconAttribute(): string
    {
        $mime = $this->mime_type;

        if (str_contains($mime, 'image/')) {
            return 'image';
        } elseif (str_contains($mime, 'video/')) {
            return 'video';
        } elseif (str_contains($mime, 'audio/')) {
            return 'audio';
        } elseif ($mime === 'application/pdf') {
            return 'pdf';
        } elseif (in_array($mime, [
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ])) {
            return 'word';
        } elseif (in_array($mime, [
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ])) {
            return 'excel';
        } elseif (in_array($mime, [
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation'
        ])) {
            return 'powerpoint';
        } elseif (str_contains($mime, 'text/')) {
            return 'document';
        } else {
            return 'file';
        }
    }

    public function isImage(): bool
    {
        return str_contains($this->mime_type, 'image/');
    }

    public function isVideo(): bool
    {
        return str_contains($this->mime_type, 'video/');
    }

    public function isAudio(): bool
    {
        return str_contains($this->mime_type, 'audio/');
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }
}
