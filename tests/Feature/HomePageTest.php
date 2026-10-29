<?php

namespace Tests\Feature;

use Tests\TestCase;

class HomePageTest extends TestCase
{
    public function test_the_billing_page_is_served(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Store Billing');
    }
}
