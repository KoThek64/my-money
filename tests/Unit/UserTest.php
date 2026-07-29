<?php

namespace App\Tests\Unit;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        $this->user = new User();
        $this->user->setEmail('test@example.com')
            ->setPassword('hashed_password');
    }

    public function testAccessors(): void
    {
        $this->assertSame('test@example.com', $this->user->getEmail());
        $this->assertSame('hashed_password', $this->user->getPassword());
    }

    public function testCreatedAtIsSetOnConstruction(): void
    {
        $this->assertNotNull($this->user->getCreatedAt());
    }

    public function testUserIdentifierIsTheEmail(): void
    {
        $this->assertSame('test@example.com', $this->user->getUserIdentifier());
    }

    public function testEveryUserHasRoleUser(): void
    {
        $this->assertSame(['ROLE_USER'], $this->user->getRoles());
    }

    public function testRolesAreMergedWithRoleUser(): void
    {
        $this->user->setRoles(['ROLE_ADMIN']);

        $this->assertContains('ROLE_ADMIN', $this->user->getRoles());
        $this->assertContains('ROLE_USER', $this->user->getRoles());
    }

    public function testRolesAreDeduplicated(): void
    {
        $this->user->setRoles(['ROLE_USER', 'ROLE_ADMIN', 'ROLE_ADMIN']);

        $roles = $this->user->getRoles();

        $this->assertCount(2, $roles);
        $this->assertSame($roles, array_unique($roles));
    }

    public function testSerializeHashesThePassword(): void
    {
        $data = $this->user->__serialize();
        $key = "\0".User::class."\0password";

        $this->assertArrayHasKey($key, $data);
        $this->assertNotSame('hashed_password', $data[$key]);
        $this->assertSame(hash('crc32c', 'hashed_password'), $data[$key]);
    }
}
