<?php

declare(strict_types=1);

namespace OpenDxp\MateExtension\Tests\Feature\Security;

use OpenDxp\MateExtension\Security\SecurityAdvisories;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

function advisories(array $feed): SecurityAdvisories
{
    return new SecurityAdvisories(new MockHttpClient(new JsonMockResponse($feed)), 'https://docs.example.com');
}

$feed = ['advisories' => [[
    'id'       => 'OPENDXP-SA-2026-007',
    'title'    => 'Workspaces opened paths outside their own folder',
    'severity' => 'medium',
    'cvss'     => 6.5,
    'url'      => 'https://docs.example.com/security/advisories/opendxp-sa-2026-007',
    'packages' => [
        ['name' => 'open-dxp/opendxp', 'affected' => '>= 1.4.0, < 1.4.5 || >= 1.5.0, < 1.5.1', 'fixed' => ['1.4.5', '1.5.1']],
        ['name' => 'open-dxp/admin-bundle', 'affected' => '< 1.5.0', 'fixed' => ['1.5.0']],
    ],
]]];

it('names an advisory once with every affected package and the fixed versions above the installed one', function () use ($feed) {
    $affecting = advisories($feed)->findAffecting([
        'open-dxp/opendxp'      => '1.4.2',
        'open-dxp/admin-bundle' => '1.4.9',
    ]);

    expect($affecting)->toHaveCount(1)
        ->and($affecting[0]['id'])->toBe('OPENDXP-SA-2026-007')
        ->and($affecting[0]['packages'])->toBe([
            ['name' => 'open-dxp/opendxp', 'installed' => '1.4.2', 'fixed' => ['1.4.5', '1.5.1']],
            ['name' => 'open-dxp/admin-bundle', 'installed' => '1.4.9', 'fixed' => ['1.5.0']],
        ]);
});

it('leaves out a fixed version, a package of another branch line and a package installed from a branch', function () use ($feed) {
    expect(advisories($feed)->findAffecting([
        'open-dxp/opendxp'      => '1.4.5',
        'open-dxp/admin-bundle' => 'dev-1.x',
    ]))->toBe([]);
});

it('returns null when the documentation site does not answer with a feed it can read', function (MockResponse $response) {
    $advisories = new SecurityAdvisories(new MockHttpClient($response), 'https://docs.example.com');

    expect($advisories->findAffecting(['open-dxp/opendxp' => '1.4.2']))->toBeNull();
})->with([
    'not found'               => fn () => new MockResponse('', ['http_code' => 404]),
    'no advisories'           => fn () => new JsonMockResponse(['items' => []]),
    'a package without range' => fn () => new JsonMockResponse(['advisories' => [['packages' => [['name' => 'open-dxp/opendxp', 'range' => '< 1.5.0']]]]]),
    'a range it cannot parse' => fn () => new JsonMockResponse(['advisories' => [['packages' => [['name' => 'open-dxp/opendxp', 'affected' => 'before 1.5']]]]]),
]);
