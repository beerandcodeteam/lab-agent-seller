<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'message_role_id' => $this->roleId('user'),
            'content' => fake()->sentence(),
        ];
    }

    public function fromUser(): static
    {
        return $this->state(fn (array $attributes): array => [
            'message_role_id' => $this->roleId('user'),
        ]);
    }

    public function fromAssistant(): static
    {
        return $this->state(fn (array $attributes): array => [
            'message_role_id' => $this->roleId('assistant'),
        ]);
    }

    /**
     * Resolve a message role id by slug, seeding the lookup if absent.
     */
    private function roleId(string $slug): int
    {
        $names = ['user' => 'Cliente', 'assistant' => 'Agente'];

        return MessageRole::firstOrCreate(
            ['slug' => $slug],
            ['name' => $names[$slug] ?? ucfirst($slug)],
        )->id;
    }
}
