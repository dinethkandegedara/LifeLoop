<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    /** @use HasFactory<\Database\Factories\TaskFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'status',
        'archived_at',
        'has_history',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => 'string',
            'archived_at' => 'datetime',
            'has_history' => 'boolean',
        ];
    }

    /**
     * Get the user that owns the task.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope query to only active tasks.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope query to only archived tasks.
     */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('status', 'archived');
    }

    /**
     * Scope query by search term across title and description.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $term = trim($term);

        return $query->where(function (Builder $q) use ($term) {
            $q->where('title', 'like', "%{$term}%")
              ->orWhere('description', 'like', "%{$term}%");
        });
    }

    /**
     * Archive the task.
     */
    public function archive(): bool
    {
        $this->status = 'archived';
        $this->archived_at = now();

        return $this->save();
    }

    /**
     * Restore an archived task to active.
     */
    public function unarchive(): bool
    {
        $this->status = 'active';
        $this->archived_at = null;

        return $this->save();
    }

    /**
     * Check if the task is archived.
     */
    public function isArchived(): bool
    {
        return $this->status === 'archived' || !is_null($this->archived_at);
    }

    /**
     * Check if the task has history that prevents permanent deletion.
     */
    public function hasHistory(): bool
    {
        return (bool) $this->has_history;
    }
}
