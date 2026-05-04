<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Job;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JobController extends Controller
{
    /**
     * Display a listing of jobs.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Job::query();

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('department')) {
            $query->where('department', $request->department);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $jobs = $query->with('applicants')->paginate(15);

        return response()->json($jobs);
    }

    /**
     * Store a newly created job.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'department' => 'required|string|max:255',
            'location' => 'required|string|max:255',
            'type' => 'required|string|max:255',
            'description' => 'nullable|string',
            'responsibilities' => 'nullable|array',
            'requirements' => 'nullable|array',
            'salary' => 'nullable|string|max:255',
            'status' => 'required|in:active,closed',
            'application_url' => 'nullable|url',
        ]);

        $job = Job::create($validated);

        return response()->json($job, 201);
    }

    /**
     * Display the specified job.
     */
    public function show(Job $job): JsonResponse
    {
        return response()->json($job->load('applicants'));
    }

    /**
     * Update the specified job.
     */
    public function update(Request $request, Job $job): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'department' => 'required|string|max:255',
            'location' => 'required|string|max:255',
            'type' => 'required|string|max:255',
            'description' => 'nullable|string',
            'responsibilities' => 'nullable|array',
            'requirements' => 'nullable|array',
            'salary' => 'nullable|string|max:255',
            'status' => 'required|in:active,closed',
            'application_url' => 'nullable|url',
        ]);

        $job->update($validated);

        return response()->json($job);
    }

    /**
     * Delete the specified job.
     */
    public function destroy(Job $job): JsonResponse
    {
        $job->delete();

        return response()->json(null, 204);
    }
}
