<?php

namespace App\Http\Controllers;

use App\Models\TaskType;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TaskTypeController extends Controller
{
    public function index()
    {
        return Inertia::render('master/task-types/page', [
            'taskTypes' => TaskType::orderBy('nama')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255|unique:task_types,nama',
        ]);

        TaskType::create($validated);

        return redirect()->back()->with('success', 'Task type created successfully.');
    }

    public function update(Request $request, TaskType $taskType)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255|unique:task_types,nama,' . $taskType->id,
        ]);

        $taskType->update($validated);

        return redirect()->back()->with('success', 'Task type updated successfully.');
    }

    public function destroy(TaskType $taskType)
    {
        $taskType->delete();

        return redirect()->back()->with('success', 'Task type deleted successfully.');
    }
}
