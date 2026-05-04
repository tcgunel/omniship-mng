<?php

declare(strict_types=1);

use Omniship\MNG\Message\GetCitiesRequest;
use Omniship\MNG\Message\GetCitiesResponse;
use Omniship\MNG\Message\GetDistrictsRequest;
use Omniship\MNG\Message\GetDistrictsResponse;

use function Omniship\MNG\Tests\createMockRequestFactory;
use function Omniship\MNG\Tests\createMockStreamFactory;
use function Omniship\MNG\Tests\createSequencedMockHttpClient;

it('lists cities and skips JWT fetch (CBS Info needs no Bearer)', function () {
    $captured = [];
    $body = json_encode([
        ['code' => '01', 'name' => 'Adana'],
        ['code' => '34', 'name' => 'İstanbul'],
    ], JSON_THROW_ON_ERROR);

    $request = new GetCitiesRequest(
        createSequencedMockHttpClient([['body' => $body, 'status' => 200]], $captured),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
    $request->initialize([
        'clientId' => 'cid',
        'clientSecret' => 'csec',
        'testMode' => true,
    ]);

    $response = $request->send();

    expect($response)->toBeInstanceOf(GetCitiesResponse::class)
        ->and($response->isSuccessful())->toBeTrue()
        ->and($response->getCities())->toBe([
            ['code' => 1, 'name' => 'Adana'],
            ['code' => 34, 'name' => 'İstanbul'],
        ]);

    // Only one HTTP call — no token fetch
    expect(count($captured))->toBe(1);

    $req = $captured[0];
    expect($req->getMethod())->toBe('GET')
        ->and($req->getUri()->getPath())->toEndWith('/cbsinfoapi/getcities')
        ->and($req->hasHeader('Authorization'))->toBeFalse()
        ->and($req->getHeaderLine('X-IBM-Client-Id'))->toBe('cid');
});

it('lists districts for a city code, padding to two digits', function () {
    $captured = [];
    $body = json_encode([
        ['cityCode' => '01', 'cityName' => 'Adana', 'code' => '85', 'name' => 'Çukurova'],
        ['cityCode' => '01', 'cityName' => 'Adana', 'code' => '86', 'name' => 'Sarıçam'],
    ], JSON_THROW_ON_ERROR);

    $request = new GetDistrictsRequest(
        createSequencedMockHttpClient([['body' => $body, 'status' => 200]], $captured),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
    $request->initialize([
        'clientId' => 'cid',
        'clientSecret' => 'csec',
        'cityCode' => 1,
        'testMode' => true,
    ]);

    $response = $request->send();

    expect($response)->toBeInstanceOf(GetDistrictsResponse::class)
        ->and($response->isSuccessful())->toBeTrue()
        ->and($response->getDistricts())->toBe([
            ['cityCode' => 1, 'cityName' => 'Adana', 'code' => 85, 'name' => 'Çukurova'],
            ['cityCode' => 1, 'cityName' => 'Adana', 'code' => 86, 'name' => 'Sarıçam'],
        ]);

    expect($captured[0]->getUri()->getPath())->toEndWith('/cbsinfoapi/getdistricts/01');
});
