<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_not_found_page_is_friendly(): void
    {
        $this->get('/ruta-que-no-existe')
            ->assertNotFound()
            ->assertSee('Esta página no existe')
            ->assertSee('404');
    }
}
