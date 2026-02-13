<?php

declare(strict_types=1);

test('platform liveness check returns healthy', function () {
    $response = $this->getJson('/platform/health');

    $response->assertStatus(200)
        ->assertJsonStructure(['status', 'timestamp'])
        ->assertJson(['status' => 'healthy']);
});

test('platform liveness live endpoint returns healthy', function () {
    $response = $this->getJson('/platform/health/live');

    $response->assertStatus(200)
        ->assertJson(['status' => 'healthy']);
});

test('platform readiness check returns healthy with component checks', function () {
    $response = $this->getJson('/platform/health/ready');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'status',
            'timestamp',
            'checks' => [
                'database' => ['status'],
                'cache' => ['status'],
                'queue' => ['status'],
            ],
        ])
        ->assertJson(['status' => 'healthy']);
});

test('tenant liveness check returns healthy', function () {
    $response = $this->getJson('/tenant/health');

    $response->assertStatus(200)
        ->assertJson(['status' => 'healthy']);
});

test('tenant readiness check returns healthy', function () {
    $response = $this->getJson('/tenant/health/ready');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'status',
            'timestamp',
            'checks' => [
                'database',
                'cache',
                'queue',
            ],
        ]);
});
