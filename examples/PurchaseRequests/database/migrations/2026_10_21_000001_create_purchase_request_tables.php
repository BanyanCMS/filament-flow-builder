<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The example's own tables. The plugin itself has none: a designed flow is
 * plain JSON, and where an item is in it is a small JSON value (a Position).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Flows people design in the builder (the templates).
        Schema::create('approval_flows', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->json('flow');
            $table->timestamps();
        });

        // Who in YOUR system stands behind each approver or team key of the definition.
        Schema::create('approver_assignments', function (Blueprint $table): void {
            $table->id();
            $table->string('approver_key');
            $table->foreignId('user_id');
            $table->unique(['approver_key', 'user_id']);
        });

        Schema::create('purchase_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->unsignedInteger('amount');
            $table->string('department');
            $table->foreignId('requester_id');
            $table->foreignId('approval_flow_id');
            // The flow as it was when the request was submitted: editing the template later never moves requests already in it.
            $table->json('flow')->nullable();
            // Where it is: {"state": "step|returned|finished", "step_id": "…"}.
            $table->json('position')->nullable();
            $table->string('status')->default('draft');
            $table->string('current_step')->nullable();
            $table->timestamp('step_reached_at')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();
        });

        // One row per person's decision on a step (several are needed for "all", "a number" or one per team).
        Schema::create('purchase_request_votes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_request_id');
            $table->string('step_id');
            $table->foreignId('user_id');
            $table->string('team')->nullable();
            $table->string('decision');
            $table->text('note')->nullable();
            $table->timestamps();
        });

        // The audit log: every move, who made it, and why.
        Schema::create('purchase_request_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_request_id');
            $table->string('event');
            $table->string('step_id')->nullable();
            $table->string('step_name')->nullable();
            $table->foreignId('actor_id')->nullable();
            $table->json('details')->nullable();
            $table->timestamp('created_at');
        });
    }
};
