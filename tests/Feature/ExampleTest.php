<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_health_check(): void
    {
        $this->get('/up')->assertStatus(200);
    }
}
