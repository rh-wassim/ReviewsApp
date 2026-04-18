<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\AnalyzeRequest;
use App\Services\HuggingFaceService;
use Illuminate\Http\JsonResponse;

class AnalyzeController
{
    public function __invoke(AnalyzeRequest $request, HuggingFaceService $hf): JsonResponse
    {
        $result = $hf->analyze($request->input('text'));

        return response()->json([
            'sentiment' => $result['sentiment'],
            'score'     => $result['score'],
            'topics'    => $result['topics'],
        ]);
    }
}
