<?php

namespace App\Http\Controllers\Search;

use App\Http\Requests\Api\SearchingRequest;
use App\Http\Resources\Initial\CountryCollection;
use App\Http\Resources\Initial\FilterResource;
use App\Http\Resources\Initial\FirmCollection;
use App\Http\Resources\Initial\MainResource;
use App\Http\Resources\Initial\ResponseResource;
use App\Http\Resources\Initial\SearchCollection;
use App\Models\Candidate;
use App\Models\Country;
use App\Models\FilterPqeLevel;
use App\Models\FilterPracticeArea;
use App\Models\FilterSpecialism;
use App\Models\FilterSubSpecialism;
use App\Models\FilterType;
use App\Models\Firm;
use App\Models\Search;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class SearchController extends Controller
{
    /**
     * Render layout for search page
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function dashboard(Request $request)
    {
        return view('layouts.search');
    }

    /**
     * Get initial data (filters, regions, firms, etc.)
     *
     * @return ResponseResource
     */
    public function initial()
    {
        $filter['levels'] = FilterPqeLevel::select('id', 'title')
            ->get();

        $filter['types'] = FilterType::select('id', 'title', 'flag')
            ->get()->prepend(['id' => 0, 'title' => 'All firms', 'flag' => 'type', ]);

        $filter['areas'] = FilterPracticeArea::select('id', 'title')
            ->get();

        $filter['specialisms'] = FilterSpecialism::select('id', 'title', 'area_id')
            ->get()->prepend(['id' => 0, 'title' => 'All']);

        $filter['subspecialisms'] = FilterSubSpecialism::select('id', 'title', 'specialism_id')
            ->get()->prepend(['id' => 0, 'title' => 'All']);

        $firms = Firm::with(['salaries' => function($query) {
            $query->select('id', 'firm_id', 'pqe', 'min', 'max');
        }])->select('id', 'title')->get();

        $countries = Country::with(['regions' => function($query) {
            $query->select('id', 'title', 'country_id');
        }])->select('id', 'title')->get();


        return new ResponseResource([
            'user'      => Auth::user(),
            'filters'   => $filter,
            'firms'     => $firms,
            'countries' => $countries,
        ]);
    }

    /**
     * Searching mechanism
     *
     * @param SearchingRequest $request
     * @return ResponseResource
     */
    public function searching(SearchingRequest $request)
    {
        $query = $this->searchMechanism($request);
        $candidates = $query->get();
        $selected = $query->count();

        /* PQE GROUP */
        $grouped  = $candidates->groupBy('pqe');
        $groupPQE = $this->pgeCount($grouped);

        /* FIRM GROUP */
        $grouped  = $candidates->groupBy('firm.id');
        $groupFirm = $this->firmsCount($grouped);

        /* REGION GROUP */
        $regions = $this->regionCount($candidates);

        /* UPDATING SAVE SEARCH */
        $search = Search::where('id', $request->search_id)->first();
        if (isset($search)){
            $search->update([
                'last_run'  => Carbon::now(),
                'results'   => $selected
            ]);
        }

        return new ResponseResource([
            'candidates_total'      => Candidate::count(),
            'candidates_selected'   => &$selected,
            'pqe'                   => &$groupPQE,
            'firms'                 => $groupFirm->values(),
            'regions'               => &$regions,
            'candidates'            => &$candidates,
        ]);
    }

    /**
     * Make search and count selected candidates
     *
     * @param SearchingRequest $request
     * @return ResponseResource
     */
    public function preSearch(SearchingRequest $request)
    {
        $query = $this->searchMechanism($request);

        return new ResponseResource([
            'candidates'   => $query->count(),
        ]);
    }

    /**
     * Searching logic
     *
     * @param $request
     * @return $this|mixed
     */
    private function searchMechanism($request)
    {
        /* type & ranking conditions */
        $data = $this->typesConvertor($request);

        /* SEARCHING */
        return Candidate::with('jobs')->with('regions')
            ->with(['firm' => function($query) {
                $query->select('id', 'title', 'type', 'ranking');
            }])
            ->with(['links' => function($query) {
                $query->select('id', 'candidate_id', 'link');
            }])
            ->with(['specialisms' => function($query) {
                $query->select('id', 'candidate_id', 'area_id', 'specialism_id', 'subspecialism_id');
            }])
            ->when($request->firms, function ($query) use ($request) {
                return $query->whereIn('firm_id', $request->firms);
            })
            ->when($request->regions, function ($query) use ($request) {
                return $query->whereHas('regions', function ($query)  use ($request) {
                    $query->whereIn('region_id', $request->regions);
                });
            })
            ->when($request->filters['area'], function ($query) use ($request) {
                return $query->whereHas('specialisms', function ($query)  use ($request) {
                    $query->where('area_id', $request->filters['area']);
                });
            })
            ->when($request->filters['specialisms'], function ($query) use ($request) {
                return $query->whereHas('specialisms', function ($query)  use ($request) {
                    $query->whereIn('specialism_id', $request->filters['specialisms']);
                });
            })
            ->when($request->filters['subspecialisms'], function ($query) use ($request) {
                return $query->whereHas('specialisms', function ($query)  use ($request) {
                    $query->whereIn('subspecialism_id', $request->filters['subspecialisms']);
                });
            })
            ->when($request->filters['levels'], function ($query) use ($request) {
                if (in_array(10, $request->filters['levels'])) {
                    return $query->where(function ($query) use ($request) {
                        $query->whereIn('pqe', $request->filters['levels'])->orWhere('pqe', '>', 10);
                    });
                } else {
                    return $query->whereIn('pqe', $request->filters['levels']);
                }
            })
            ->when($request->filters['types'], function ($query) use ($data) {
                if(isset($data['type'])){
                    return $query->whereHas('firm', function ($query)  use ($data) {
                        $query->whereIn('type', $data['type']);
                    });
                }
            })
            ->when($request->filters['types'], function ($query) use ($data) {
                if(isset($data['rank'])){
                    return $query->whereHas('firm', function ($query)  use ($data) {
                        $i = 0;
                        foreach ($data['rank'] as $rank){
                            if($i == 0){
                                $query->whereBetween('ranking', [ $rank['min'], $rank['max'] ] );
                            } else {
                                $query->orWhereBetween('ranking', [ $rank['min'], $rank['max'] ]);
                            } $i++;
                        }
                    });
                }
            });
    }

    /**
     * Group and count candidates in regions
     *
     * @param $candidates
     * @return array
     */
    private function regionCount($candidates)
    {
        $regions = [];
        foreach ($candidates as &$candidate){
            foreach ($candidate['regions'] as &$region){
                if (!isset($regions[$region['region_id']])){
                    $regions[$region['region_id']] = [
                        'id'            => $region['region_id'],
                        'title'         => $region['region']['title'],
                        'country_id'    => $region['country_id'],
                        'total' => 1,
                    ];
                } else {
                    $regions[$region['region_id']]['total']++;
                }
            }
        }

        return $regions;
    }

    /**
     * pge collection method
     *
     * @param $grouped
     * @return mixed
     */
    private function pgeCount($grouped)
    {
        return  $grouped->map(function ($item, $key) {
            return collect($item)->count();
        });
    }

    /**
     * firms collection method
     *
     * @param $grouped
     * @return mixed
     */
    private function firmsCount($grouped)
    {
        return  $grouped->map(function ($item, $key) {
            return [
                'id' => $key,
                'title' => $item->pluck('firm.title')[0],
                'total' => collect($item)->count()
            ];
        });
    }

    /**
     * convert types and ranking filters
     *
     * @param $request
     * @return mixed
     */
    private function typesConvertor($request)
    {
        /* type & ranking conditions */
        if(!empty($request->filters['types'])){
            $types_rankings = FilterType::whereIn('id', $request->filters['types'])->get();
        } else {
            $types_rankings = FilterType::all();
        }

        foreach($types_rankings as $key => $item){
            if($item['flag'] == 'type'){
                $data['type'][] = $item['id'];
            } else {
                $data['rank'][$key]['min'] = $item['min'];
                $data['rank'][$key]['max'] = $item['max'];
            }
        }

        return $data;
    }
}