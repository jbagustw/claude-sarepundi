<?php

namespace Tests\Feature\Newsletter;

use App\Models\NewsletterSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsletterModuleTest extends TestCase
{
    use RefreshDatabase;

    private function fromFrontend(): static
    {
        return $this->withHeaders(['Origin' => 'http://localhost:3000']);
    }

    public function test_guest_can_subscribe_with_a_valid_email(): void
    {
        $response = $this->fromFrontend()->postJson('/api/newsletter/subscribe', [
            'email' => 'reader@example.com',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('newsletter_subscribers', ['email' => 'reader@example.com']);
    }

    public function test_subscribing_twice_with_the_same_email_does_not_error_or_duplicate(): void
    {
        $this->fromFrontend()->postJson('/api/newsletter/subscribe', ['email' => 'reader@example.com'])
            ->assertCreated();

        $this->fromFrontend()->postJson('/api/newsletter/subscribe', ['email' => 'reader@example.com'])
            ->assertCreated();

        $this->assertSame(1, NewsletterSubscriber::where('email', 'reader@example.com')->count());
    }

    public function test_subscribe_rejects_an_invalid_email(): void
    {
        $response = $this->fromFrontend()->postJson('/api/newsletter/subscribe', ['email' => 'not-an-email']);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('email');
    }

    public function test_subscribe_rejects_a_missing_email(): void
    {
        $response = $this->fromFrontend()->postJson('/api/newsletter/subscribe', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('email');
    }
}
