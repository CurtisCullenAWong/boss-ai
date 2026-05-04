<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        /*
        |--------------------------------------------------------------------------
        | Jobs Table
        |--------------------------------------------------------------------------
        */

        Schema::create('jobs', function (Blueprint $table) {
            $table->uuid('id')
                ->primary();

            $table->text('title');
            $table->string('department');
            $table->string('location');
            $table->string('type');

            $table->text('description');

            $table->json('responsibilities');

            $table->json('requirements');

            $table->text('salary')->nullable();

            $table->dateTime('posted_date')
                ->useCurrent();

            $table->enum('status', ['active', 'closed'])
                ->default('active');

            $table->text('application_url')
                ->nullable();

            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | Job Applicants Table
        |--------------------------------------------------------------------------
        */

        Schema::create('job_applicants', function (Blueprint $table) {
            $table->uuid('id')
                ->primary();

            $table->uuid('job_id')
                ->nullable();

            $table->foreign('job_id')
                ->references('id')
                ->on('jobs')
                ->nullOnDelete();

            $table->text('first_name');
            $table->text('last_name');
            $table->string('email');

            $table->text('phone')
                ->nullable();

            $table->text('cover_letter')
                ->nullable();

            $table->text('resume_url');

            $table->text('linkedin_url')
                ->nullable();

            $table->text('portfolio_url')
                ->nullable();

            $table->enum('status', ['Pending', 'Reviewing', 'Interviewing', 'Offer', 'Hired', 'Rejected', 'Withdrawn'])
                ->default('Pending');

            $table->dateTime('applied_at')
                ->useCurrent();

            $table->dateTime('updated_at')
                ->nullable();

            $table->text('updated_by')
                ->nullable();

            $table->dateTime('created_at')
                ->useCurrent();
        });

        DB::statement("
            ALTER TABLE job_applicants
            ADD CONSTRAINT job_applicants_email_check
            CHECK (email LIKE '%@%')
        ");

        /*
        |--------------------------------------------------------------------------
        | Indexes
        |--------------------------------------------------------------------------
        */

        Schema::table('jobs', function (Blueprint $table) {
            $table->index('status');
            $table->index('posted_date');
            $table->index('department');
            $table->index('type');
            $table->index('location');
        });

        Schema::table('job_applicants', function (Blueprint $table) {
            $table->index('job_id');
            $table->index('status');
            $table->index('applied_at');
            $table->index('updated_at');
            $table->unique(['job_id', 'email']);
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('job_applicants');
        Schema::dropIfExists('jobs');
    }
};