<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\JobApplicant;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $title
 * @property string $department
 * @property string $location
 * @property string $type
 * @property string $description
 * @property array $responsibilities
 * @property array $requirements
 * @property string $salary
 * @property Carbon $posted_date
 * @property string $status
 * @property string|null $application_url
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Job extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'title',
        'department',
        'location',
        'type',
        'description',
        'responsibilities',
        'requirements',
        'salary',
        'posted_date',
        'status',
        'application_url',
    ];

    protected $casts = [
        'posted_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'responsibilities' => 'array',
        'requirements' => 'array',
    ];

    /**
     * Get all applicants for this job
     */
    public function applicants(): HasMany
    {
        return $this->hasMany(JobApplicant::class, 'job_id');
    }
}
