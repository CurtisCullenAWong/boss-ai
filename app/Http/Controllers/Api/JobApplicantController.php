<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Models\JobApplicant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JobApplicantController extends Controller
{
    /**
     * Display a listing of all applicants.
     */
    public function index(Request $request): JsonResponse
    {
        $query = JobApplicant::query();

        if ($request->has('job_id')) {
            $query->where('job_id', $request->job_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('email')) {
            $query->where('email', 'like', "%{$request->email}%");
        }

        $applicants = $query->with('job')->paginate(15);

        return response()->json($applicants);
    }

    /**
     * Store a newly created applicant.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'job_id' => 'nullable|uuid|exists:jobs,id',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'nullable|string|max:20',
            'cover_letter' => 'nullable|string',
            'resume_url' => 'required|url',
            'linkedin_url' => 'nullable|url',
            'portfolio_url' => 'nullable|url',
        ]);

        $applicant = JobApplicant::create($validated);

        return response()->json($applicant->load('job'), 201);
    }

    /**
     * Display the specified applicant.
     */
    public function show(JobApplicant $applicant): JsonResponse
    {
        return response()->json($applicant->load('job'));
    }

    /**
     * Update the specified applicant.
     */
    public function update(Request $request, JobApplicant $applicant): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'nullable|string|max:20',
            'cover_letter' => 'nullable|string',
            'resume_url' => 'required|url',
            'linkedin_url' => 'nullable|url',
            'portfolio_url' => 'nullable|url',
            'status' => 'required|in:Pending,Reviewing,Interviewing,Offer,Hired,Rejected,Withdrawn',
            'updated_by' => 'nullable|string|max:255',
        ]);

        $applicant->update($validated);

        return response()->json($applicant);
    }

    /**
     * Delete the specified applicant.
     */
    public function destroy(JobApplicant $applicant): JsonResponse
    {
        $applicant->delete();

        return response()->json(null, 204);
    }

    /**
     * Get applicants for a specific job.
     */
    public function byJob(Job $job): JsonResponse
    {
        $applicants = $job->applicants()->paginate(15);

        return response()->json($applicants);
    }

    /**
     * Update applicant status.
     */
    public function updateStatus(Request $request, JobApplicant $applicant): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:Pending,Reviewing,Interviewing,Offer,Hired,Rejected,Withdrawn',
        ]);

        $applicant->update($validated);

        return response()->json($applicant);
    }
}
