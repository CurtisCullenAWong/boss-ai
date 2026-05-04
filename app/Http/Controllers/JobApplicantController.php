<?php

namespace App\Http\Controllers;

use App\Models\Job;
use App\Models\JobApplicant;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class JobApplicantController extends Controller
{
    /**
     * Display a listing of all applicants.
     */
    public function index(): View
    {
        $applicants = JobApplicant::with('job')->paginate(15);
        return view('applicants.index', compact('applicants'));
    }

    /**
     * Show the form for creating a new application.
     */
    public function create(Job $job = null): View
    {
        return view('applicants.create', compact('job'));
    }

    /**
     * Store a newly created applicant in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'job_id' => 'nullable|uuid|exists:jobs,id',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:job_applicants,email',
            'phone' => 'nullable|string|max:20',
            'cover_letter' => 'nullable|string',
            'resume_url' => 'required|url',
            'linkedin_url' => 'nullable|url',
            'portfolio_url' => 'nullable|url',
        ]);

        JobApplicant::create($validated);

        return redirect()->route('applicants.index')
            ->with('success', 'Application submitted successfully.');
    }

    /**
     * Display the specified applicant.
     */
    public function show(JobApplicant $applicant): View
    {
        $applicant->load('job');
        return view('applicants.show', compact('applicant'));
    }

    /**
     * Show the form for editing the specified applicant.
     */
    public function edit(JobApplicant $applicant): View
    {
        return view('applicants.edit', compact('applicant'));
    }

    /**
     * Update the specified applicant in storage.
     */
    public function update(Request $request, JobApplicant $applicant): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:job_applicants,email,' . $applicant->id,
            'phone' => 'nullable|string|max:20',
            'cover_letter' => 'nullable|string',
            'resume_url' => 'required|url',
            'linkedin_url' => 'nullable|url',
            'portfolio_url' => 'nullable|url',
            'status' => 'required|in:Pending,Reviewing,Interviewing,Offer,Hired,Rejected,Withdrawn',
            'updated_by' => 'nullable|string|max:255',
        ]);

        $applicant->update($validated);

        return redirect()->route('applicants.show', $applicant)
            ->with('success', 'Application updated successfully.');
    }

    /**
     * Remove the specified applicant from storage.
     */
    public function destroy(JobApplicant $applicant): RedirectResponse
    {
        $applicant->delete();

        return redirect()->route('applicants.index')
            ->with('success', 'Application deleted successfully.');
    }

    /**
     * Display applicants for a specific job.
     */
    public function byJob(Job $job): View
    {
        $applicants = $job->applicants()->paginate(15);
        return view('applicants.by-job', compact('job', 'applicants'));
    }

    /**
     * Update the status of an applicant.
     */
    public function updateStatus(Request $request, JobApplicant $applicant): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:Pending,Reviewing,Interviewing,Offer,Hired,Rejected,Withdrawn',
        ]);

        $applicant->update($validated);

        return redirect()->back()
            ->with('success', 'Application status updated successfully.');
    }
}
