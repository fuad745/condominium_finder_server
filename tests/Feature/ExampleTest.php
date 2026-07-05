<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_root_redirects_into_the_app_or_panel(): void
    {
        $this->get('/')->assertRedirect();
    }
}
