<?php

namespace App\Imports;

use App\Models\CandidateJob;
use App\Models\CandidateLink;
use App\Models\CandidateLocation;
use App\Models\CandidateSubSpecialism;
use App\Models\City;
use App\Models\Country;
use App\Models\FilterSpecialism;
use App\Models\FilterSubSpecialism;
use App\Models\Firm;
use App\Models\FirmLocation;
use App\Models\Region;
use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Concerns\ToCollection;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use App\Models\Candidate;

class CandidateImport implements ToCollection, WithHeadingRow, WithCalculatedFormulas, WithBatchInserts, WithChunkReading, ShouldQueue
{
    /**
     * Parse Candidates from xls
     *
     * @param Collection $rows
     * @throws \Exception
     */
    public function collection(Collection $rows)
    {

        foreach ($rows as $row){

            if(!Candidate::where('ref_num',$row['refno'])->first()){
                /* new candidate */
                $firm = Firm::where('title', $row['firm'])->first();

                if(isset($firm)){
                    /* firm location/candidate location */
                    $country = Country::where('title', $row['country'])->first();
                    if(isset($country)){
                        $region  = Region::where('title', $row['region'])->where('country_id', $country->id)->first();
                    }

                    if(isset($region)){
                        $city    = City::where('title', $row['city'])->first();
                    }

                    if(isset($city)){
                        $firm_location = FirmLocation::where('firm_id', $firm['id'])->where('country_id', $country['id'])->where('region_id', $region['id'])->where('city_id', $city['id'])->first();
                    }

                    if(isset($firm_location)){

                        $candidate = Candidate::create([
                            'ref_num'           => trim($row['refno']),
                            'forename'          => trim($row['forename']),
                            'surname'           => trim($row['surname']),
                            'firm_id'           => trim($firm['id']),
                            'law_society'       => trim($row['law_society']),
                            'law_society_link'  => trim($row['law_society_link']),
                            'lang'              => trim($row['languages']),
                            'admitted_date'     => ($row['admitted_date']) ? \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($row['admitted_date']) : null,
                            'pqe'               => trim($row['pqe']),
                            'website'           => trim($row['website']),
                            'job_title'         => trim($row['title']),
                            'email'             => trim($row['email']),
                            'workphone'         => trim($row['workphone']),
                            'mobile_phone'      => trim($row['mobile_phone']),
                            'linked_in'         => trim($row['linkedin']),
                            'gender'            => trim($row['gender']),
                            'changed_job_date'  => ($row['changed_job_date']) ? \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($row['changed_job_date']) : null,
                            'university'        => trim($row['university']),
                            'school'            => trim($row['school']),
                        ]);


                        CandidateLocation::create([
                            'candidate_id'  => $candidate['id'],
                            'location_id'   => $firm_location['id'],
                        ]);



                        /* specialisms / sub_specialisms */
                        if($row['sub_specialisms']){
                            $raw_subs = explode(",",$row['sub_specialisms']);
                            foreach ($raw_subs as $item){
                                $specialism = FilterSpecialism::where('title', substr($item, 0, strpos($item, ':')))->first();
                                if($specialism){
                                    $sub = FilterSubSpecialism::with('specialism')->where('title', substr($item, strpos($item, ':') + 1))->where('specialism_id', $specialism->id)->first();
                                    CandidateSubSpecialism::firstOrCreate([
                                        'candidate_id'      => $candidate['id'],
                                        'area_id'           => $specialism['area_id'],
                                        'specialism_id'     => $sub['specialism_id'],
                                        'subspecialism_id'  => $sub['id'],
                                    ]);
                                }
                            }
                        }

                        if($row['specialisms']){
                            $specialisms = FilterSpecialism::whereIn('title', explode(",",$row['specialisms']))->get();
                            foreach ($specialisms as $specialism){
                                CandidateSubSpecialism::firstOrCreate([
                                    'candidate_id'  => $candidate['id'],
                                    'area_id'       => $specialism['area_id'],
                                    'specialism_id' => $specialism['id'],
                                ]);
                            }
                        }

                        /* candidate_jobs */
                        if($row['1st_previous_firm']){
                            CandidateJob::create([
                                'candidate_id'  => $candidate['id'],
                                'previous_firm' => trim($row['1st_previous_firm']),
                                'previous_date' => trim($row['1st_previous_dates']),
                            ]);
                        }
                        if($row['2nd_previous_firm']){
                            CandidateJob::create([
                                'candidate_id'  => $candidate['id'],
                                'previous_firm' => $row['2nd_previous_firm'],
                                'previous_date' => $row['2nd_previous_dates'],
                            ]);
                        }

                        /* candidate_links */
                        $links = array($row['link_1'], $row['link_2'], $row['link_3'], $row['link_4'], $row['link_5']);
                        foreach ($links as $link){
                            if($link){
                                CandidateLink::create([
                                    'candidate_id'  => $candidate['id'],
                                    'link'          => trim($link)
                                ]);
                            }
                        }

                    }
                }
            }
        }
    }

    public function headingRow(): int
    {
        return 1;
    }

    public function batchSize(): int
    {
        return 100;
    }

    public function chunkSize(): int
    {
        return 500;
    }
}
