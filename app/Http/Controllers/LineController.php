<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLineRequest;
use App\Http\Requests\UpdateLineRequest;
use App\Http\Resources\LineResource;
use App\Models\Line;

class LineController extends Controller
{
    public function index()
    {
        $lines = Line::paginate();

        return LineResource::collection($lines);
    }

    public function store(StoreLineRequest $request)
    {
        $input = $request->validated();

        $line = Line::create($input);

        return new LineResource($line);
    }

    public function show(Line $line)
    {
        return new LineResource($line);
    }

    public function update(UpdateLineRequest $request, Line $line)
    {
        $input = $request->validated();

        $line->update($input);

        return new LineResource($line);
    }

    public function destroy(Line $line)
    {
        $line->delete();

        return response()->noContent();
    }
}
