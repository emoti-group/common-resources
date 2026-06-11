<?php

declare(strict_types=1);

namespace Tests\Unit\ModelCasts;

use Emoti\CommonResources\ModelCasts\UuidModelCast;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class UuidModelCastTest extends TestCase
{
    private UuidModelCast $cast;
    private Model $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cast = new UuidModelCast();
        $this->model = $this->createStub(Model::class);
    }

    public function test_get_converts_string_to_uuid_interface(): void
    {
        $uuidString = '550e8400-e29b-41d4-a716-446655440000';

        $result = $this->cast->get($this->model, 'id', $uuidString, []);

        $this->assertInstanceOf(UuidInterface::class, $result);
        $this->assertSame($uuidString, $result->toString());
    }

    public function test_get_returns_uuid_interface_unchanged(): void
    {
        $uuid = Uuid::fromString('550e8400-e29b-41d4-a716-446655440000');

        $result = $this->cast->get($this->model, 'id', $uuid, []);

        $this->assertSame($uuid, $result);
    }

    public function test_get_returns_null_for_null_value(): void
    {
        $result = $this->cast->get($this->model, 'id', null, []);

        $this->assertNull($result);
    }

    public function test_set_converts_uuid_interface_to_string(): void
    {
        $uuid = Uuid::fromString('550e8400-e29b-41d4-a716-446655440000');

        $result = $this->cast->set($this->model, 'id', $uuid, []);

        $this->assertIsString($result['id']);
        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $result['id']);
    }

    public function test_set_stores_plain_string_unchanged(): void
    {
        $uuidString = '550e8400-e29b-41d4-a716-446655440000';

        $result = $this->cast->set($this->model, 'id', $uuidString, []);

        $this->assertSame($uuidString, $result['id']);
    }
}
