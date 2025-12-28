<?php

namespace Tests\Feature;

use Tests\TestCase;

class StatusTest extends TestCase
{
    /** @test */
    public function check_unauthenticated_status()
    {
        $response = $this->getJson('/api/admin/dashboard');
        
        dd([
            'status' => $response->getStatusCode(),
            'content' => $response->getContent(),
            'headers' => $response->headers
        ]);
    }
}