<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $job_id
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string|null $phone
 * @property string|null $cover_letter
 * @property string $resume_url
 * @property string|null $linkedin_url
 * @property string|null $portfolio_url
 * @property string $status
 * @property Carbon $applied_at
 * @property Carbon|null $updated_at
 * @property string|null $updated_by
 * @property Carbon $created_at
 */
class JobApplicant extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'job_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'cover_letter',
        'resume_url',
        'linkedin_url',
        'portfolio_url',
        'status',
        'updated_by',
    ];

    protected $casts = [
        'applied_at' => 'datetime',
        'updated_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    /**
     * Get the job this applicant applied for
     */
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class, 'job_id');
    }

    /**
     * Get the full name of the applicant
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
