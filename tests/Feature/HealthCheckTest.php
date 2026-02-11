<?php

declare(strict_types=1);

test('platform health check returns ok', function () {
    $response = $this->getJson('/platform/health');

    $response->assertStatus(200)
        ->assertJsonStructure(['status', 'timestamp'])
        ->assertJson(['status' => 'ok']);
});

test('tenant health check returns ok', function () {
    $response = $this->getJson('/tenant/health');

    $response->assertStatus(200)
        ->assertJson(['status' => 'ok']);
});
