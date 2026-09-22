<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Shop;
use App\Models\Subcategory;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\TaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    private Product $product;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TaxonomySeeder::class);

        $this->shop = Shop::create([
            'owner_id' => User::factory()->create()->id,
            'name' => 'Quarry Works',
            'name_key' => 'quarry works',
            'slug' => 'quarry-works',
            'locality' => 'Jaipur',
            'pincode' => '302001',
            'address' => 'Stone Market',
            'status' => Shop::STATUS_ACTIVE,
        ]);

        $this->product = Product::create([
            'shop_id' => $this->shop->id,
            'subcategory_id' => Subcategory::first()->id,
            'unit_id' => Unit::where('code', 'meter')->value('id'),
            'name' => 'Quarry marble',
            'title' => 'Quarry marble slab',
            'description' => 'Listing',
            'unit_count' => 1,
            'price_paise' => 90000,
            'status' => Product::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        ProductImage::create(['product_id' => $this->product->id, 'path' => 'products/q.jpg', 'sort_order' => 1]);

        $this->client = User::factory()->create();
    }

    private function token(User $user, array $abilities = ['client']): string
    {
        return $user->createToken('api', $abilities)->plainTextToken;
    }

    public function test_repeated_enquiries_reopen_the_same_conversation(): void
    {
        $token = $this->token($this->client);

        $first = $this->withApiToken($token)
            ->postJson('/api/v1/client/conversations', ['product_id' => $this->product->id])
            ->assertCreated()
            ->json('data.id');

        $second = $this->withApiToken($token)
            ->postJson('/api/v1/client/conversations', ['product_id' => $this->product->id])
            ->assertCreated()
            ->json('data.id');

        $this->assertSame($first, $second);
        $this->assertSame(1, Conversation::count());
    }

    public function test_a_shop_owner_cannot_open_a_conversation_with_their_own_shop(): void
    {
        $token = $this->token($this->shop->owner);

        $this->withApiToken($token)
            ->postJson('/api/v1/client/conversations', ['product_id' => $this->product->id])
            ->assertStatus(422)
            ->assertJsonPath('code', 'OWN_SHOP');
    }

    public function test_sending_the_same_client_message_id_twice_stores_one_message(): void
    {
        $token = $this->token($this->client);

        $conversationId = $this->withApiToken($token)
            ->postJson('/api/v1/client/conversations', ['product_id' => $this->product->id])
            ->json('data.id');

        foreach ([1, 2] as $attempt) {
            $this->withApiToken($token)
                ->postJson("/api/v1/conversations/{$conversationId}/messages", [
                    'body' => 'Is this available in 2 metre slabs?',
                    'client_message_id' => 'retry-1',
                ])
                ->assertCreated();
        }

        $this->assertSame(1, Message::where('conversation_id', $conversationId)->count());
    }

    public function test_only_participants_can_read_a_thread(): void
    {
        $clientToken = $this->token($this->client);

        $conversationId = $this->withApiToken($clientToken)
            ->postJson('/api/v1/client/conversations', ['product_id' => $this->product->id])
            ->json('data.id');

        $stranger = $this->token(User::factory()->create());

        $this->withApiToken($stranger)
            ->getJson("/api/v1/conversations/{$conversationId}/messages")
            ->assertForbidden();

        $shopToken = $this->token($this->shop->owner, ['shop']);

        $this->withApiToken($shopToken)
            ->getJson("/api/v1/conversations/{$conversationId}/messages")
            ->assertOk();
    }

    public function test_the_read_cursor_never_moves_backwards(): void
    {
        $token = $this->token($this->client);

        $conversationId = $this->withApiToken($token)
            ->postJson('/api/v1/client/conversations', ['product_id' => $this->product->id])
            ->json('data.id');

        foreach (['One', 'Two'] as $body) {
            $this->withApiToken($token)->postJson("/api/v1/conversations/{$conversationId}/messages", ['body' => $body]);
        }

        $latest = Message::where('conversation_id', $conversationId)->max('id');

        $this->withApiToken($token)
            ->patchJson("/api/v1/conversations/{$conversationId}/read", ['message_id' => $latest])
            ->assertOk()
            ->assertJsonPath('data.last_read_message_id', $latest);

        // An older cursor attempt is ignored rather than rewinding.
        $this->withApiToken($token)
            ->patchJson("/api/v1/conversations/{$conversationId}/read", ['message_id' => $latest - 1])
            ->assertOk()
            ->assertJsonPath('data.last_read_message_id', $latest);
    }

    public function test_messages_from_another_conversation_are_rejected_as_a_cursor(): void
    {
        $token = $this->token($this->client);

        $conversationId = $this->withApiToken($token)
            ->postJson('/api/v1/client/conversations', ['product_id' => $this->product->id])
            ->json('data.id');

        $otherProduct = Product::create([
            'shop_id' => $this->shop->id,
            'subcategory_id' => $this->product->subcategory_id,
            'unit_id' => $this->product->unit_id,
            'name' => 'Second slab',
            'title' => 'Second slab listing',
            'description' => 'Listing',
            'unit_count' => 1,
            'price_paise' => 70000,
            'status' => Product::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        $otherConversationId = $this->withApiToken($token)
            ->postJson('/api/v1/client/conversations', ['product_id' => $otherProduct->id])
            ->json('data.id');

        $foreignMessage = Message::create([
            'conversation_id' => $otherConversationId,
            'sender_type' => 'user',
            'sender_id' => $this->client->id,
            'body' => 'foreign',
        ]);

        $this->withApiToken($token)
            ->patchJson("/api/v1/conversations/{$conversationId}/read", ['message_id' => $foreignMessage->id])
            ->assertStatus(422);
    }
}
