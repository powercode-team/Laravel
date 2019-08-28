<?php

namespace App\Http\Controllers\Search;

use App\Http\Requests\Api\UpdateSearchRequest;
use App\Http\Resources\Initial\ResponseResource;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SaveSearchRequest;
use App\Http\Requests\Api\ShowSearchRequest;
use App\Models\Search;

class CriteriaController extends Controller
{
    /**
     * Save search criterias
     *
     * @param SaveSearchRequest $request
     * @return ResponseResource
     */
    public function save(SaveSearchRequest $request)
    {
        $search = Search::create([
            'user_id'   => $request->user,
            'title'      => $request->title,
            'results'   => $request->results,
            'last_run'  => $request->last_run,
            'criterias' => json_encode($request->except(['user', 'title', 'results', 'last_run'])),
        ]);

        return new ResponseResource([
            'search' => $search,
        ]);
    }

    /**
     * Show search criterias
     *
     * @param ShowSearchRequest $request
     * @return ResponseResource
     */
    public function showAll(ShowSearchRequest $request)
    {
        $search = Search::where('user_id', $request->user_id)->get();

        return new ResponseResource([
            'search_collection' => $search,
        ]);
    }

    /**
     * Update search criteris by ID
     *
     * @param UpdateSearchRequest $request
     * @return ResponseResource
     */
    public function update(UpdateSearchRequest $request)
    {
        $search = Search::where('id', $request->id)->where('user_id', $request->user)->first();

        if($search){
            $search->update([
                'user_id'   => $request->user,
                'title'      => $request->title,
                'results'   => $request->results,
                'last_run'  => $request->last_run,
                'criterias' => json_encode($request->except(['id', 'user', 'title', 'results', 'last_run']))
            ]);

            return new ResponseResource(['success' => true]);
        } else {
            return new ResponseResource(['errors' => [ 'no_item' => [ 'no this item in DB' ] ]]);
        }
    }

    /**
     * Delete search criterias by ID
     *
     * @param Request $request
     * @return ResponseResource
     */
    public function delete(Request $request)
    {
        $item = Search::where('id', $request->id)->first();

        if($item){
            $item->delete();

            return new ResponseResource(['success' => true]);
        } else {
            return new ResponseResource(['errors' => [ 'no_item' => [ 'no this item in DB' ] ]]);
        }
    }
}
