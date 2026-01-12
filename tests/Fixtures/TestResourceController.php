<?php

namespace Knuckles\Scribe\Tests\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class TestResourceController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @response {
     *   "index_resource": true
     * }
     *
     * @return Response
     */
    public function index()
    {
        return [
            'index_resource' => true,
        ];
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store(Request $request) {}

    /**
     * Display the specified resource.
     *
     * @response {
     *   "show_resource": true
     * }
     *
     * @param int $id
     *
     * @return Response
     */
    public function show($id)
    {
        return [
            'show_resource' => true,
        ];
    }

    /**
     * Update the specified resource in storage.
     *
     * @param int $id
     *
     * @return Response
     */
    public function update(Request $request, $id) {}

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     *
     * @return Response
     */
    public function destroy($id) {}
}
