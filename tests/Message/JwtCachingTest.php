<?php

declare(strict_types=1);

use Omniship\MNG\Message\GetCitiesRequest;

use function Omniship\MNG\Tests\createInMemoryCache;
use function Omniship\MNG\Tests\createMockRequestFactory;
use function Omniship\MNG\Tests\createMockStreamFactory;
use function Omniship\MNG\Tests\createSequencedMockHttpClient;

// Minimal way to exercise fetchJwt: pick any request that requires a JWT and
// observe whether the token endpoint is hit on the second send. CBS-only
// requests don't need JWT, so use a request that does — the simplest is
// reusing the tracking endpoint via a custom subclass would be overkill;
// instead, drive two CreateRecipient calls (which require JWT) sharing the
// same cache and assert the captured request stream.

use Omniship\Common\Address;
use Omniship\MNG\Message\CreateRecipientRequest;

function jwtTokenResponse(string $jwt = 'cached.tok'): array
{
    return ['body' => json_encode(['jwt' => $jwt], JSON_THROW_ON_ERROR), 'status' => 200];
}

function recipientOk(): array
{
    return [
        'body' => json_encode(['shipperBranchCode' => '1234'], JSON_THROW_ON_ERROR),
        'status' => 200,
    ];
}

function buildRecipientReq(array $responses, array &$captured = []): CreateRecipientRequest
{
    return new CreateRecipientRequest(
        createSequencedMockHttpClient($responses, $captured),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
}

function recipientParams(array $extra = []): array
{
    return array_merge([
        'clientId' => 'cid',
        'clientSecret' => 'csec',
        'customerNumber' => 'cust',
        'password' => 'pw',
        'testMode' => true,
        'shipTo' => new Address(
            name: 'X',
            street1: 'Addr',
            city: 'Adana',
            district: 'Seyhan',
            phone: '5551234567',
        ),
        'recipientCityCode' => 1,
        'recipientDistrictCode' => 100,
    ], $extra);
}

it('mints token once per request when no cache provided', function () {
    $captured = [];
    $request = buildRecipientReq(
        [jwtTokenResponse(), recipientOk()],
        $captured,
    );
    $request->initialize(recipientParams());

    $request->send();

    expect(count($captured))->toBe(2)
        ->and($captured[0]->getUri()->getPath())->toEndWith('/mngapi/api/token')
        ->and($captured[1]->getUri()->getPath())->toEndWith('/createRecipient');
});

it('reuses cached JWT on subsequent send calls', function () {
    $cache = createInMemoryCache();

    // Two recipients sharing one cache. Only ONE token fetch should happen.
    $captured = [];
    $request1 = buildRecipientReq(
        [jwtTokenResponse('cached-jwt-1'), recipientOk(), recipientOk()],
        $captured,
    );
    $request1->initialize(recipientParams(['tokenCache' => $cache]));
    $request1->send();

    $request2 = buildRecipientReq(
        [recipientOk()], // no token response in this client — would error if a token call were made
        $captured,
    );
    $request2->initialize(recipientParams(['tokenCache' => $cache]));
    $request2->send();

    // Only one /token call across both sends
    $tokenCalls = array_filter(
        $captured,
        fn ($req) => str_ends_with($req->getUri()->getPath(), '/mngapi/api/token'),
    );
    expect(count($tokenCalls))->toBe(1);

    // And the second send used the cached value via the Authorization header
    $secondCreate = $captured[2] ?? null;
    expect($secondCreate)->not->toBeNull()
        ->and($secondCreate->getHeaderLine('Authorization'))->toBe('Bearer cached-jwt-1');
});

it('does not call /token for CBS Info requests (no JWT needed)', function () {
    $captured = [];
    $request = new GetCitiesRequest(
        createSequencedMockHttpClient(
            [['body' => json_encode([['code' => '01', 'name' => 'Adana']], JSON_THROW_ON_ERROR), 'status' => 200]],
            $captured,
        ),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
    $request->initialize(['clientId' => 'cid', 'clientSecret' => 'csec', 'testMode' => true]);

    $request->send();

    expect(count($captured))->toBe(1);
});
