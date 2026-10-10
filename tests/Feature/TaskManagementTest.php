<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $this->otherUser = User::factory()->create([
            'email_verified_at' => now(),
        ]);
    }

    public function test_unauthenticated_user_cannot_access_tasks(): void
    {
        $response = $this->get('/tasks');
        $response->assertRedirect('/login');

        $response = $this->post('/tasks', ['title' => 'Sample Task']);
        $response->assertRedirect('/login');
    }

    public function test_user_can_view_tasks_index_page(): void
    {
        Task::factory()->count(3)->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->get('/tasks');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Tasks/Index')
            ->has('tasks.data', 3)
            ->has('counts')
            ->where('counts.active', 3)
            ->where('counts.archived', 0)
        );
    }

    public function test_user_can_create_task_with_title_and_optional_description(): void
    {
        $response = $this->actingAs($this->user)->post('/tasks', [
            'title' => 'Design user schedule view',
            'description' => 'Add lavender cards and responsive filters',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Task created successfully.');

        $this->assertDatabaseHas('tasks', [
            'user_id' => $this->user->id,
            'title' => 'Design user schedule view',
            'description' => 'Add lavender cards and responsive filters',
            'status' => 'active',
            'archived_at' => null,
            'has_history' => false,
        ]);
    }

    public function test_task_ownership_is_server_enforced_ignoring_client_user_id(): void
    {
        // Even if client attempts to pass another user's ID
        $response = $this->actingAs($this->user)->post('/tasks', [
            'user_id' => $this->otherUser->id,
            'title' => 'Security Audit',
        ]);

        $response->assertRedirect();

        $task = Task::where('title', 'Security Audit')->first();
        $this->assertNotNull($task);
        $this->assertEquals($this->user->id, $task->user_id, 'Task must belong to authenticated user, not spoofed user_id');
    }

    public function test_task_validation_requires_title_and_enforces_limits(): void
    {
        $response = $this->actingAs($this->user)->post('/tasks', [
            'title' => '',
        ]);

        $response->assertSessionHasErrors(['title']);

        $responseLong = $this->actingAs($this->user)->post('/tasks', [
            'title' => str_repeat('a', 256),
        ]);

        $responseLong->assertSessionHasErrors(['title']);

        $responseLongDesc = $this->actingAs($this->user)->post('/tasks', [
            'title' => 'Valid Title',
            'description' => str_repeat('a', 2001),
        ]);

        $responseLongDesc->assertSessionHasErrors(['description']);
    }

    public function test_user_can_update_their_own_task(): void
    {
        $task = Task::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Original Title',
            'description' => 'Original Desc',
        ]);

        $response = $this->actingAs($this->user)->put("/tasks/{$task->id}", [
            'title' => 'Updated Title',
            'description' => 'Updated Desc',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Task updated successfully.');

        $task->refresh();
        $this->assertEquals('Updated Title', $task->title);
        $this->assertEquals('Updated Desc', $task->description);
    }

    public function test_user_can_archive_and_unarchive_task(): void
    {
        $task = Task::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'active',
        ]);

        // Archive
        $response = $this->actingAs($this->user)->patch("/tasks/{$task->id}/archive");
        $response->assertRedirect();
        $response->assertSessionHas('success', 'Task archived successfully.');

        $task->refresh();
        $this->assertEquals('archived', $task->status);
        $this->assertNotNull($task->archived_at);
        $this->assertTrue($task->isArchived());

        // Unarchive / restore
        $response = $this->actingAs($this->user)->patch("/tasks/{$task->id}/unarchive");
        $response->assertRedirect();
        $response->assertSessionHas('success', 'Task restored to active.');

        $task->refresh();
        $this->assertEquals('active', $task->status);
        $this->assertNull($task->archived_at);
        $this->assertFalse($task->isArchived());
    }

    public function test_task_without_history_can_be_permanently_deleted(): void
    {
        $task = Task::factory()->create([
            'user_id' => $this->user->id,
            'has_history' => false,
        ]);

        $response = $this->actingAs($this->user)->delete("/tasks/{$task->id}");

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Task permanently deleted.');

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_task_with_history_cannot_be_deleted_permanently(): void
    {
        $task = Task::factory()->withHistory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->delete("/tasks/{$task->id}");

        $response->assertRedirect();
        $response->assertSessionHasErrors(['delete']);

        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
    }

    public function test_user_can_filter_tasks_by_status(): void
    {
        Task::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'status' => 'active',
        ]);

        Task::factory()->count(3)->archived()->create([
            'user_id' => $this->user->id,
        ]);

        // Active only (default)
        $responseActive = $this->actingAs($this->user)->get('/tasks?status=active');
        $responseActive->assertOk();
        $responseActive->assertInertia(fn ($page) => $page
            ->has('tasks.data', 2)
            ->where('counts.active', 2)
            ->where('counts.archived', 3)
            ->where('counts.total', 5)
        );

        // Archived only
        $responseArchived = $this->actingAs($this->user)->get('/tasks?status=archived');
        $responseArchived->assertOk();
        $responseArchived->assertInertia(fn ($page) => $page
            ->has('tasks.data', 3)
        );

        // All tasks
        $responseAll = $this->actingAs($this->user)->get('/tasks?status=all');
        $responseAll->assertOk();
        $responseAll->assertInertia(fn ($page) => $page
            ->has('tasks.data', 5)
        );
    }

    public function test_user_can_search_tasks_by_title_and_description(): void
    {
        Task::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Kubernetes Pod Deployment',
            'description' => 'Setup ingress controller',
        ]);

        Task::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Weekly grocery run',
            'description' => 'Buy organic milk and eggs',
        ]);

        $responseSearchTitle = $this->actingAs($this->user)->get('/tasks?status=all&search=Kubernetes');
        $responseSearchTitle->assertInertia(fn ($page) => $page
            ->has('tasks.data', 1)
            ->where('tasks.data.0.title', 'Kubernetes Pod Deployment')
        );

        $responseSearchDesc = $this->actingAs($this->user)->get('/tasks?status=all&search=organic');
        $responseSearchDesc->assertInertia(fn ($page) => $page
            ->has('tasks.data', 1)
            ->where('tasks.data.0.title', 'Weekly grocery run')
        );
    }

    public function test_cross_user_isolation_user_cannot_access_or_modify_another_users_task(): void
    {
        $otherTask = Task::factory()->create([
            'user_id' => $this->otherUser->id,
            'title' => 'Secret Other User Task',
        ]);

        // Attempting to update
        $responseUpdate = $this->actingAs($this->user)->put("/tasks/{$otherTask->id}", [
            'title' => 'Hacked Title',
        ]);
        $responseUpdate->assertForbidden();

        // Attempting to archive
        $responseArchive = $this->actingAs($this->user)->patch("/tasks/{$otherTask->id}/archive");
        $responseArchive->assertForbidden();

        // Attempting to unarchive
        $responseUnarchive = $this->actingAs($this->user)->patch("/tasks/{$otherTask->id}/unarchive");
        $responseUnarchive->assertForbidden();

        // Attempting to delete
        $responseDelete = $this->actingAs($this->user)->delete("/tasks/{$otherTask->id}");
        $responseDelete->assertForbidden();

        // Other user's task was untouched
        $this->assertDatabaseHas('tasks', [
            'id' => $otherTask->id,
            'title' => 'Secret Other User Task',
        ]);

        // Index listing should not include other user's tasks
        $responseIndex = $this->actingAs($this->user)->get('/tasks?status=all');
        $responseIndex->assertInertia(fn ($page) => $page
            ->has('tasks.data', 0)
        );
    }
}
