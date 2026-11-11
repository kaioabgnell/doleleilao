<?php

declare(strict_types=1);

it('responde o health check com banco e redis ok', function () {
    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('checks.database', true)
        ->assertJsonPath('checks.redis', true)
        ->assertJsonStructure(['serverNow']);
});
