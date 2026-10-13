<?php

namespace Tests\Unit;

use App\Services\PerformanceAnalyzer;
use Tests\TestCase;

class PerformanceAnalyzerTest extends TestCase
{
    private function realPosts(): array
    {
        $json = json_decode(file_get_contents(base_path(config('performance.synthetic_path'))), true);

        return $json['posts'];
    }

    private function group(array $analysis, string $dimension, string $value): array
    {
        foreach ($analysis[$dimension]['groups'] as $group) {
            if ($group['value'] === $value) {
                return $group;
            }
        }
        $this->fail("Group {$dimension}={$value} not found");
    }

    private function record(string $id, array $overrides = []): array
    {
        return array_merge([
            'post_id' => $id, 'platform' => 'Facebook', 'content_format' => 'Image', 'campaign_goal' => 'Awareness',
            'impressions' => 1000, 'reach' => 500, 'likes' => 40, 'comments' => 5, 'shares' => 5, 'link_clicks' => 10,
        ], $overrides);
    }

    public function test_text_and_conversion_groups_are_insufficient_with_no_recommendation(): void
    {
        $analysis = (new PerformanceAnalyzer)->analyze($this->realPosts());

        foreach ([['content_format', 'Text'], ['campaign_goal', 'Conversion']] as [$dimension, $value]) {
            $group = $this->group($analysis, $dimension, $value);
            $this->assertSame(2, $group['n_valid'], "{$value} n");
            $this->assertSame('insufficient', $group['confidence']);
            $this->assertNull($group['observation']);
            $this->assertNull($group['recommendation']);
        }
    }

    public function test_reel_confidence_is_computed_from_its_actual_cv(): void
    {
        $posts = $this->realPosts();
        $reels = array_values(array_filter($posts, fn ($p) => $p['content_format'] === 'Reel'));
        $ers = array_map(fn ($p) => ($p['likes'] + $p['comments'] + $p['shares']) / $p['reach'] * 100, $reels);
        $mean = array_sum($ers) / count($ers);
        $sd = sqrt(array_sum(array_map(fn ($x) => ($x - $mean) ** 2, $ers)) / count($ers));
        $expectedCv = $sd / $mean;
        $expectedLevel = $expectedCv <= 0.5 ? 'medium' : 'low';

        $group = $this->group((new PerformanceAnalyzer)->analyze($posts), 'content_format', 'Reel');

        $this->assertSame(11, $group['n_valid']);
        $this->assertEqualsWithDelta($mean, $group['mean_er'], 1e-9);
        $this->assertEqualsWithDelta($expectedCv, $group['cv'], 1e-9);
        $this->assertSame($expectedLevel, $group['confidence']);
        $this->assertNotNull($group['observation']);
    }

    public function test_every_group_follows_the_ladder_and_high_is_never_assigned(): void
    {
        $analysis = (new PerformanceAnalyzer)->analyze($this->realPosts());

        foreach ($analysis as $dimension) {
            $this->assertSame(30, $dimension['n_valid']);
            $this->assertSame(0, $dimension['n_excluded']);
            foreach ($dimension['groups'] as $g) {
                $expected = match (true) {
                    $g['n_valid'] < 5 => 'insufficient',
                    $g['n_valid'] < 10 => 'low',
                    $g['cv'] !== null && $g['cv'] <= 0.5 => 'medium',
                    default => 'low',
                };
                $this->assertSame($expected, $g['confidence'], $g['key']);
                $this->assertNotSame('high', $g['confidence']);
                $this->assertCount($g['n_valid'], $g['post_ids']);
                $this->assertSame($g['confidence'] === 'insufficient', $g['recommendation'] === null, $g['key']);
            }
        }
    }

    public function test_ladder_boundaries(): void
    {
        $a = new PerformanceAnalyzer;

        $this->assertSame('insufficient', $a->confidence(4, 0.1));
        $this->assertSame('low', $a->confidence(5, 0.1));
        $this->assertSame('low', $a->confidence(9, 0.1));
        $this->assertSame('medium', $a->confidence(10, 0.5));
        $this->assertSame('low', $a->confidence(10, 0.51));
        $this->assertSame('low', $a->confidence(25, null));
    }

    public function test_group_with_zero_mean_engagement_never_gets_medium(): void
    {
        $posts = [];
        for ($i = 1; $i <= 12; $i++) {
            $posts[] = $this->record("Z-{$i}", ['likes' => 0, 'comments' => 0, 'shares' => 0]);
        }

        $group = $this->group((new PerformanceAnalyzer)->analyze($posts), 'content_format', 'Image');

        $this->assertSame(12, $group['n_valid']);
        $this->assertSame(0.0, $group['mean_er']);
        $this->assertNull($group['cv']);
        $this->assertSame('low', $group['confidence']);
    }

    public function test_invalid_records_are_excluded_and_counted(): void
    {
        $posts = [
            $this->record('A-1'), $this->record('A-2'), $this->record('A-3'), $this->record('A-4'),
            $this->record('A-5', ['reach' => 0]),
            $this->record('A-6', ['likes' => null]),
        ];

        $analysis = (new PerformanceAnalyzer)->analyze($posts);
        $group = $this->group($analysis, 'content_format', 'Image');

        $this->assertSame(4, $group['n_valid']);
        $this->assertSame(2, $group['n_excluded']);
        $this->assertSame(['A-5', 'A-6'], $group['excluded_post_ids']);
        $this->assertSame('insufficient', $group['confidence']);
        $this->assertSame(2, $analysis['content_format']['n_excluded']);
    }

    public function test_group_is_compared_with_the_other_posts_in_its_dimension(): void
    {
        // Reels: ER 20% each (n=5). Images: ER 10% each (n=5).
        $posts = [];
        for ($i = 1; $i <= 5; $i++) {
            $posts[] = $this->record("R-{$i}", ['content_format' => 'Reel', 'likes' => 80, 'comments' => 10, 'shares' => 10]);
            $posts[] = $this->record("I-{$i}");
        }

        $analysis = (new PerformanceAnalyzer)->analyze($posts);
        $reel = $this->group($analysis, 'content_format', 'Reel');
        $image = $this->group($analysis, 'content_format', 'Image');

        $this->assertEqualsWithDelta(20.0, $reel['mean_er'], 1e-9);
        $this->assertEqualsWithDelta(10.0, $reel['rest_mean_er'], 1e-9);
        $this->assertEqualsWithDelta(10.0, $reel['difference_pp'], 1e-9);
        $this->assertSame('above', $reel['direction']);
        $this->assertSame('below', $image['direction']);
        $this->assertEqualsWithDelta(1.0, $reel['mean_ctr'], 1e-9);
        $this->assertSame(0.0, $reel['cv']);
    }
}
