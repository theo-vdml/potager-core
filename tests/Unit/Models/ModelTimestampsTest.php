<?php

use Potager\Limpid\Attributes\Column;
use Potager\Limpid\Model;
use Potager\Limpid\Traits\WithTimestamps;

class Post extends Model
{
    use WithTimestamps;

    #[Column]
    public ?int $id;

    #[Column]
    public string $title;
}

beforeEach(function () {
    $pdo = $this->pdo();

    $pdo->exec('
        CREATE TABLE posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        )
    ');

    $pdo->exec("
        INSERT INTO posts (title, created_at, updated_at) VALUES
        ('First Post', datetime('now'), datetime('now')),
        ('Second Post', datetime('now'), datetime('now')),
        ('Third Post', datetime('now'), datetime('now'))
    ");
});

// Test cases for validating timestamp types
test('Created and updated timestamps are DateTime instances upon creation using new', function () {
    $post = new Post();
    $post->title = "Test Post";
    $post->save();

    expect($post->createdAt)->toBeInstanceOf(DateTime::class);
    expect($post->updatedAt)->toBeInstanceOf(DateTime::class);
});

test('Created and updated timestamps are DateTime instances upon creation using create', function () {
    $post = Post::create(['title' => 'Test Post']);

    expect($post->createdAt)->toBeInstanceOf(DateTime::class);
    expect($post->updatedAt)->toBeInstanceOf(DateTime::class);
});

test('Retrieved model has DateTime instances for timestamps', function () {
    $post = Post::find(1);

    expect($post->createdAt)->toBeInstanceOf(DateTime::class);
    expect($post->updatedAt)->toBeInstanceOf(DateTime::class);
});

// Test cases for creation scenarios
test('Creating a model with new sets both createdAt and updatedAt timestamps', function () {
    $post = new Post();
    $post->title = "New Post";
    $post->save();

    expect($post->createdAt)->toEqual($post->updatedAt);
});

test('Creating a model with ::create sets both createdAt and updatedAt timestamps', function () {
    $post = Post::create(['title' => 'Test Post']);

    expect($post->createdAt)->toEqual($post->updatedAt);
});

test('Timestamps are accurate to the current time upon creation with new', function () {
    $post = new Post();
    $post->title = "Timed Post";
    $post->save();

    $now = new DateTime();
    $createdAtDiff = $now->getTimestamp() - $post->createdAt->getTimestamp();
    $updatedAtDiff = $now->getTimestamp() - $post->updatedAt->getTimestamp();

    expect($createdAtDiff)->toBeLessThan(5);
    expect($updatedAtDiff)->toBeLessThan(5);
});

test('Timestamps are accurate to the current time upon creation with ::create', function () {
    $post = Post::create(['title' => 'Timed Post']);

    $now = new DateTime();
    $createdAtDiff = $now->getTimestamp() - $post->createdAt->getTimestamp();
    $updatedAtDiff = $now->getTimestamp() - $post->updatedAt->getTimestamp();

    expect($createdAtDiff)->toBeLessThan(5);
    expect($updatedAtDiff)->toBeLessThan(5);
});

// Test cases for update scenarios
test('Updating a freshly created model updates the updatedAt timestamp', function () {
    $post = new Post();
    $post->title = "Initial Post";
    $post->save();

    $originalUpdatedAt = $post->updatedAt;
    $post->title = "Updated Post";
    $post->save();

    expect($post->updatedAt)->not->toEqual($originalUpdatedAt);
});

test('Updating a retrieved model updates the updatedAt timestamp', function () {
    $post = Post::find(1);
    $originalUpdatedAt = $post->updatedAt;

    $post->title = "Updated Post";
    $post->save();

    expect($post->updatedAt)->not->toEqual($originalUpdatedAt);
});

test('Timestamps are accurate to the current time upon update', function () {
    $post = Post::find(1);
    $post->title = "Updated Timed Post";
    $post->save();

    $now = new DateTime();
    $updatedAtDiff = $now->getTimestamp() - $post->updatedAt->getTimestamp();

    expect($updatedAtDiff)->toBeLessThan(5);
});

// Test cases for non-changing scenarios
test('Updating a model does not change the createdAt timestamp', function () {
    $post = Post::find(1);
    $originalCreatedAt = $post->createdAt;

    $post->title = "Another Update";
    $post->save();

    expect($post->createdAt)->toEqual($originalCreatedAt);
});

test('Saving a model without changes does not update the updatedAt timestamp', function () {
    $post = Post::find(1);
    $originalUpdatedAt = $post->updatedAt;

    $post->save();

    expect($post->updatedAt)->toEqual($originalUpdatedAt);
});

// Test cases for deletion scenarios
test('Deleting a model does not affect its timestamps', function () {
    $post = Post::find(1);
    $createdAt = $post->createdAt;
    $updatedAt = $post->updatedAt;

    $post->delete();

    expect($createdAt)->toEqual($post->createdAt);
    expect($updatedAt)->toEqual($post->updatedAt);
});

// Test cases for unsaved models
test('Unsaved model has null timestamps', function () {
    $post = new Post();
    $post->title = "Unsaved Post";

    expect($post->createdAt)->toBeNull();
    expect($post->updatedAt)->toBeNull();
});
