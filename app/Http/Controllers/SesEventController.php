<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SesEvent;
use App\Services\SesMapperService;

class SesEventController extends Controller
{
    protected SesMapperService $mapperService;

    public function __construct(SesMapperService $mapperService)
    {
        $this->mapperService = $mapperService;
    }

    public function transform(Request $request)
    {
        // Convert the incoming JSON to SesEvent model
        $sesEvent = new SesEvent($request->input('Records')[0]);

        // Use the service to map the SesEvent model to the desired structure
        $transformedEvent = $this->mapperService->map($sesEvent);

        // Return the transformed JSON
        return response()->json($transformedEvent);
    }
}
