<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreReviewRequest;
use App\Http\Requests\UpdateReviewRequest;
use App\Models\Review;
use App\Services\HuggingFaceService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController
{
    use AuthorizesRequests;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Review::query()->with('user:id,name,email')->latest();

        if (! $user->isAdmin()) {
            $query->where('user_id', $user->id);
        }

        return response()->json([
            'data' => $query->get(),
        ]);
    }

    public function store(StoreReviewRequest $request, HuggingFaceService $hf): JsonResponse
    {
        $analysis = $hf->analyze($request->input('content'));

        $review = Review::create([
            'user_id'   => $request->user()->id,
            'content'   => $request->input('content'),
            'sentiment' => $analysis['sentiment'],
            'score'     => $analysis['score'],
            'topics'    => $analysis['topics'],
        ]);

        return response()->json($review, 201);
    }

    public function show(Request $request, Review $review): JsonResponse
    {
        $this->authorize('view', $review);
        return response()->json($review->load('user:id,name,email'));
    }

    public function update(UpdateReviewRequest $request, Review $review, HuggingFaceService $hf): JsonResponse
    {
        $this->authorize('update', $review);

        $analysis = $hf->analyze($request->input('content'));

        $review->update([
            'content'   => $request->input('content'),
            'sentiment' => $analysis['sentiment'],
            'score'     => $analysis['score'],
            'topics'    => $analysis['topics'],
        ]);

        return response()->json($review);
    }

    public function destroy(Request $request, Review $review): JsonResponse
    {
        $this->authorize('delete', $review);
        $review->delete();
        return response()->json(['message' => 'Supprimé']);
    }
}
