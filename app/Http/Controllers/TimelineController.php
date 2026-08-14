<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectTimeline;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class TimelineController extends Controller
{
    /**
     * Display a listing of timelines.
     */
    public function index(): Response
    {
        $timelines = Cache::remember('timelines.index', 300, function () {
            return ProjectTimeline::with([
                'project:id,name,status,team_id',
                'project.team:id,name,color',
                'creator:id,name'
            ])
                ->select(
                    'id',
                    'project_id',
                    'type',
                    'phase',
                    'title',
                    'description',
                    'start_date',
                    'end_date',
                    'status',
                    'sprint_number',
                    'deliverables',
                    'created_by',
                    'created_at'
                )
                ->latest()
                ->limit(100)
                ->get()
                ->map(function ($timeline) {
                    return [
                        'id' => $timeline->id,
                        'project' => [
                            'id' => $timeline->project->id,
                            'name' => $timeline->project->name,
                            'status' => $timeline->project->status,
                            'team' => $timeline->project->team ? [
                                'name' => $timeline->project->team->name,
                                'color' => $timeline->project->team->color,
                            ] : null,
                        ],
                        'type' => $timeline->type,
                        'phase' => $timeline->phase,
                        'title' => $timeline->title,
                        'description' => $timeline->description,
                        'start_date' => $timeline->start_date?->format('Y-m-d'),
                        'end_date' => $timeline->end_date?->format('Y-m-d'),
                        'status' => $timeline->status,
                        'sprint_number' => $timeline->sprint_number,
                        'deliverables' => $timeline->deliverables,
                        'creator' => $timeline->creator?->name,
                        'created_at' => $timeline->created_at->format('d M Y'),
                    ];
                });
        });

        $projects = Cache::remember('projects.for-timelines', 600, function () {
            return Project::with('team:id,name,color')
                ->select('id', 'name', 'status', 'team_id')
                ->where('status', '!=', 'cancelled')
                ->orderBy('name')
                ->get();
        });

        return Inertia::render('timelines/page', [
            'timelines' => $timelines,
            'projects' => $projects,
        ]);
    }

    /**
     * Store a newly created timeline.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', ProjectTimeline::class);

        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'type' => 'required|in:sprint,phase,milestone,event',
            'phase' => 'nullable|in:planning,backlog_refinement,analysis,design,development,testing,code_review,deployment,release,retrospective',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'required|in:pending,in_progress,completed,delayed,cancelled',
            'sprint_number' => 'nullable|integer|min:1',
            'story_points' => 'nullable|integer|min:0',
            'deliverables' => 'nullable|array',
        ]);

        ProjectTimeline::create([
            'project_id' => $validated['project_id'],
            'type' => $validated['type'],
            'phase' => $validated['phase'] ?? null,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
            'status' => $validated['status'],
            'sprint_number' => $validated['sprint_number'] ?? null,
            'deliverables' => $validated['deliverables'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        Cache::forget('timelines.index');

        return back()->with('success', 'Timeline created successfully!');
    }

    /**
     * Update the specified timeline.
     */
    public function update(Request $request, ProjectTimeline $timeline): RedirectResponse
    {
        $this->authorize('update', $timeline);

        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'type' => 'required|in:sprint,phase,milestone,event',
            'phase' => 'nullable|in:planning,backlog_refinement,analysis,design,development,testing,code_review,deployment,release,retrospective',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'required|in:pending,in_progress,completed,delayed,cancelled',
            'sprint_number' => 'nullable|integer|min:1',
            'deliverables' => 'nullable|array',
        ]);

        $timeline->update([
            'project_id' => $validated['project_id'],
            'type' => $validated['type'],
            'phase' => $validated['phase'] ?? null,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
            'status' => $validated['status'],
            'sprint_number' => $validated['sprint_number'] ?? null,
            'deliverables' => $validated['deliverables'] ?? null,
        ]);

        Cache::forget('timelines.index');

        return back()->with('success', 'Timeline updated successfully!');
    }

    /**
     * Remove the specified timeline.
     */
    public function destroy(Request $request, ProjectTimeline $timeline): RedirectResponse
    {
        $this->authorize('delete', $timeline);

        $timeline->delete();

        Cache::forget('timelines.index');

        return back()->with('success', 'Timeline deleted successfully!');
    }
}
