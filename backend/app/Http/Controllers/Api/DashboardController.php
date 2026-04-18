<?php

namespace App\Http\Controllers\Api;

use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController
{
    protected function baseQuery(Request $request)
    {
        $user = $request->user();
        $q = Review::query();
        if (! $user->isAdmin()) {
            $q->where('user_id', $user->id);
        }
        return $q;
    }

    public function stats(Request $request): JsonResponse
    {
        $total = (clone $this->baseQuery($request))->count();

        $positive = (clone $this->baseQuery($request))->where('sentiment', 'positive')->count();
        $negative = (clone $this->baseQuery($request))->where('sentiment', 'negative')->count();
        $neutral  = (clone $this->baseQuery($request))->where('sentiment', 'neutral')->count();

        $avgScore = (float) (clone $this->baseQuery($request))->avg('score');

        return response()->json([
            'total'            => $total,
            'positive_count'   => $positive,
            'negative_count'   => $negative,
            'neutral_count'    => $neutral,
            'positive_percent' => $total ? round($positive * 100 / $total, 2) : 0,
            'negative_percent' => $total ? round($negative * 100 / $total, 2) : 0,
            'neutral_percent'  => $total ? round($neutral  * 100 / $total, 2) : 0,
            'average_score'    => (int) round($avgScore),
        ]);
    }

    public function recentReviews(Request $request): JsonResponse
    {
        $reviews = $this->baseQuery($request)
            ->with('user:id,name,email')
            ->latest()
            ->limit(5)
            ->get();

        return response()->json(['data' => $reviews]);
    }

    public function topics(Request $request): JsonResponse
    {
        $rows = $this->baseQuery($request)->whereNotNull('topics')->pluck('topics');

        $counts = [];
        foreach ($rows as $topics) {
            if (is_string($topics)) {
                $topics = json_decode($topics, true) ?: [];
            }
            foreach ((array) $topics as $t) {
                $counts[$t] = ($counts[$t] ?? 0) + 1;
            }
        }

        arsort($counts);
        $top = array_slice($counts, 0, 3, true);

        $result = [];
        foreach ($top as $topic => $count) {
            $result[] = ['topic' => $topic, 'count' => $count];
        }

        return response()->json(['data' => $result]);
    }
}
