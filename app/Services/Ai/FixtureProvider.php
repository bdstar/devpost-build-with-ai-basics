<?php

namespace App\Services\Ai;

/**
 * Demo Fixtures mode: deterministic, pre-written outputs from resources/fixtures. No API calls.
 * Every output is tagged provenance=demo_fixture so the UI can label it.
 */
class FixtureProvider implements AiProvider
{
    public const PROVENANCE = 'demo_fixture';

    public function profile(array $facts, string $text): ProfileDraft
    {
        $fixture = $this->fixture('profile.json');
        $name = $facts['business_name']['value'] ?? 'the business';

        $fields = [];
        foreach ($fixture['assumptions'] as $key => $value) {
            $fields[$key] = str_replace('{business_name}', $name, $value);
        }

        return new ProfileDraft($fields, self::PROVENANCE);
    }

    protected function fixture(string $file): array
    {
        $path = resource_path('fixtures/'.$file);
        $data = is_file($path) ? json_decode(file_get_contents($path), true) : null;
        if (! is_array($data)) {
            throw new AiException("Demo fixture {$file} is missing or invalid.");
        }

        return $data;
    }
}
