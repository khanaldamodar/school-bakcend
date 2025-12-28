<?php

namespace Tests\Feature;

use Tests\TestCase;

class QuickTest extends TestCase
{
    /** @test */
    public function basic_application_test()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    /** @test */
    public function api_root_works()
    {
        $response = $this->get('/api');

        $response->assertStatus(200);
    }
}