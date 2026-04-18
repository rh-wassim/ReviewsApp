<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class HuggingFaceService
{
    /**
     * Topic keyword lexicon (French + English) for simple extraction.
     */
    protected array $topicKeywords = [
        'prix'      => ['prix', 'cher', 'coût', 'tarif', 'abordable', 'price', 'cost', 'expensive', 'cheap'],
        'qualité'   => ['qualité', 'solide', 'cassé', 'fragile', 'robuste', 'quality', 'broken', 'durable'],
        'livraison' => ['livraison', 'colis', 'expédition', 'reçu', 'délai', 'delivery', 'shipping', 'arrived', 'package'],
        'service'   => ['service', 'support', 'équipe', 'client', 'aidé', 'staff', 'helpful', 'rude'],
        'produit'   => ['produit', 'article', 'objet', 'fonctionne', 'design', 'product', 'item', 'feature'],
        'site'      => ['site', 'application', 'appli', 'checkout', 'interface', 'website', 'app', 'login'],
        'remboursement' => ['remboursement', 'retour', 'échange', 'refund', 'return'],
    ];

    /**
     * Analyze text: returns [sentiment, score (0..100), topics].
     */
    public function analyze(string $text): array
    {
        $topics = $this->extractTopics($text);

        try {
            $result = $this->callHuggingFace($text);
            if ($result !== null) {
                return array_merge($result, ['topics' => $topics]);
            }
        } catch (Throwable $e) {
            Log::warning('HuggingFace analyze failed: '.$e->getMessage());
        }

        $fallback = $this->ruleBasedSentiment($text);
        return array_merge($fallback, ['topics' => $topics]);
    }

    protected function callHuggingFace(string $text): ?array
    {
        $token = config('services.huggingface.token');
        $model = config('services.huggingface.model');
        $url   = rtrim(config('services.huggingface.endpoint'), '/').'/'.$model;
        $timeout = (int) config('services.huggingface.timeout', 15);

        if (empty($token) || $token === 'your_token_here') {
            return null;
        }

        $response = Http::withToken($token)
            ->timeout($timeout)
            ->acceptJson()
            ->post($url, [
                'inputs'  => $text,
                'options' => ['wait_for_model' => true],
            ]);

        if (! $response->successful()) {
            Log::warning('HuggingFace HTTP error', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return null;
        }

        $data = $response->json();

        $predictions = is_array($data) && isset($data[0]) && is_array($data[0])
            ? (is_array($data[0][0] ?? null) ? $data[0] : $data)
            : null;

        if (! $predictions) {
            return null;
        }

        $best = collect($predictions)->sortByDesc('score')->first();
        if (! isset($best['label'])) {
            return null;
        }

        return [
            'sentiment' => $this->normalizeLabel($best['label']),
            'score'     => (int) round(((float) ($best['score'] ?? 0)) * 100),
        ];
    }

    protected function normalizeLabel(string $label): string
    {
        $l = strtolower($label);
        return match (true) {
            str_contains($l, 'pos') || $l === 'label_2' => 'positive',
            str_contains($l, 'neg') || $l === 'label_0' => 'negative',
            default => 'neutral',
        };
    }

    /**
     * Simple rule-based fallback sentiment.
     */
    protected function ruleBasedSentiment(string $text): array
    {
        $positive = [
            'good','great','excellent','amazing','love','perfect','awesome','fantastic','happy','best','nice','wonderful','satisfied','recommend',
            'bon','bien','super','génial','parfait','magnifique','heureux','satisfait','recommande','adore','aime','rapide','efficace','merveilleux',
        ];
        $negative = [
            'bad','terrible','awful','worst','hate','broken','poor','disappointing','slow','late','rude','waste','horrible','unhappy',
            'mauvais','nul','déçu','décevant','cassé','lent','pire','déteste','arnaque','catastrophique','médiocre','inacceptable',
        ];

        $lower = strtolower($text);
        $pos = 0;
        $neg = 0;
        foreach ($positive as $w) { $pos += substr_count($lower, $w); }
        foreach ($negative as $w) { $neg += substr_count($lower, $w); }

        $total = $pos + $neg;
        if ($total === 0) {
            return ['sentiment' => 'neutral', 'score' => 50];
        }
        if ($pos > $neg) {
            return ['sentiment' => 'positive', 'score' => (int) round($pos * 100 / $total)];
        }
        if ($neg > $pos) {
            return ['sentiment' => 'negative', 'score' => (int) round($neg * 100 / $total)];
        }
        return ['sentiment' => 'neutral', 'score' => 50];
    }

    protected function extractTopics(string $text): array
    {
        $lower = strtolower($text);
        $found = [];
        foreach ($this->topicKeywords as $topic => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($lower, $kw)) {
                    $found[] = $topic;
                    break;
                }
            }
        }
        return array_values(array_unique($found));
    }
}
