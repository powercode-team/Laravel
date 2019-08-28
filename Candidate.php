<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Candidate extends Model
{
    protected $fillable = [
        'forename', 'surname', 'firm_id', 'email', 'pqe', 'status', 'admitted_date', 'ref_num', 'workphone', 'mobile_phone',
        'website', 'linked_in', 'job_title', 'gender', 'law_society', 'law_society_link',
        'lang', 'changed_job_date', 'university', 'school'
    ];

    /**
     * Get firm details
     */
    public function firm()
    {
        return $this->belongsTo(Firm::class);
    }

    /**
     * Get all links
     *
     * @return $this
     */
    public function links()
    {
        return $this->hasMany(CandidateLink::class);
    }

    /**
     * Get all links
     *
     * @return $this
     */
    public function jobs()
    {
        return $this->hasMany(CandidateJob::class);
    }

    /**
     * Get all locations
     *
     * @return $this
     */
    public function locations()
    {
        return $this->hasMany(CandidateLocation::class);
    }

    /**
     * Get all regions for location.
     */
    public function regions()
    {
        return $this->hasManyThrough(FirmLocation::class, CandidateLocation::class, 'candidate_id', 'id', 'id' , 'location_id');
    }

    /**
     * Get all specialisms
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function specialisms()
    {
        return $this->hasMany(CandidateSubSpecialism::class);
    }

    /**
     * Get candidates pactic areas.
     */
    public function areasDetails()
    {
        return $this->hasManyThrough(FilterPracticeArea::class, CandidateSubSpecialism::class, 'candidate_id', 'id', 'id', 'area_id');
    }

    /**
     * Get candidates specialisms.
     */
    public function specialismsDetails()
    {
        return $this->hasManyThrough(FilterSpecialism::class, CandidateSubSpecialism::class, 'candidate_id', 'id', 'id', 'specialism_id');
    }

    /**
     * Get candidates sub-specialisms.
     */
    public function subSpecialismsDetails()
    {
        return $this->hasManyThrough(FilterSubSpecialism::class, CandidateSubSpecialism::class, 'candidate_id', 'id', 'id', 'subspecialism_id');
    }
}
