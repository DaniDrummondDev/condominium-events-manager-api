<?php

declare(strict_types=1);

use App\Infrastructure\MultiTenancy\TenantContext;
use Application\Auth\Contracts\TenantUserRepositoryInterface;
use Application\Billing\Contracts\FeatureResolverInterface;
use Application\Shared\Contracts\EventDispatcherInterface;
use Application\Shared\Contracts\NotificationServiceInterface;
use Application\Unit\Contracts\ResidentRepositoryInterface;
use Application\Unit\Contracts\UnitRepositoryInterface;
use Application\Unit\DTOs\InviteResidentDTO;
use Application\Unit\DTOs\ResidentDTO;
use Application\Unit\UseCases\InviteResident;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Unit\Entities\Unit;
use Domain\Unit\Enums\UnitType;

afterEach(fn () => Mockery::close());

function inviteResidentTenantContext(): TenantContext
{
    return new TenantContext(
        tenantId: Uuid::generate()->value(),
        tenantSlug: 'condo-test',
        tenantName: 'Condominio Test',
        tenantType: 'vertical',
        tenantStatus: 'active',
        databaseName: 'tenant_condo_test',
        resolvedAt: new DateTimeImmutable,
    );
}

function buildActiveUnit(?Uuid $blockId = null): Unit
{
    return Unit::create(
        Uuid::generate(),
        $blockId,
        '101',
        1,
        UnitType::Apartment,
    );
}

test('creates TenantUser and Resident and sends notification', function () {
    $tenantContext = inviteResidentTenantContext();
    $unit = buildActiveUnit(Uuid::generate());
    $unitId = $unit->id();

    $unitRepo = Mockery::mock(UnitRepositoryInterface::class);
    $unitRepo->shouldReceive('findById')->andReturn($unit);
    $unitRepo->shouldReceive('countByTenant')->andReturn(5);

    $residentRepo = Mockery::mock(ResidentRepositoryInterface::class);
    $residentRepo->shouldReceive('countActiveByUnitId')->andReturn(0);
    $residentRepo->shouldReceive('save')->once();

    $tenantUserRepo = Mockery::mock(TenantUserRepositoryInterface::class);
    $tenantUserRepo->shouldReceive('findByEmail')->andReturnNull();
    $tenantUserRepo->shouldReceive('save')->once();
    $tenantUserRepo->shouldReceive('saveInvitationToken')->once();

    $featureResolver = Mockery::mock(FeatureResolverInterface::class);
    $featureResolver->shouldReceive('featureLimit')
        ->with(Mockery::type(Uuid::class), 'max_users')
        ->andReturn(100);
    $featureResolver->shouldReceive('featureLimit')
        ->with(Mockery::type(Uuid::class), 'max_residents_per_unit')
        ->andReturn(10);

    $notificationService = Mockery::mock(NotificationServiceInterface::class);
    $notificationService->shouldReceive('send')
        ->once()
        ->withArgs(function (string $channel, string $to, string $template, array $data) {
            return $channel === 'email'
                && $to === 'morador@test.com'
                && $template === 'resident-invitation'
                && $data['name'] === 'Morador Teste'
                && $data['condominium_name'] === 'Condominio Test'
                && isset($data['token'])
                && isset($data['expires_at']);
        });

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatchAll')->once();

    $useCase = new InviteResident(
        $unitRepo, $residentRepo, $tenantUserRepo,
        $featureResolver, $notificationService,
        $eventDispatcher, $tenantContext,
    );

    $dto = new InviteResidentDTO(
        unitId: $unitId->value(),
        name: 'Morador Teste',
        email: 'morador@test.com',
        phone: '11999990000',
        document: null,
        roleInUnit: 'owner',
    );

    $result = $useCase->execute($dto);

    expect($result)->toBeInstanceOf(ResidentDTO::class)
        ->and($result->name)->toBe('Morador Teste')
        ->and($result->email)->toBe('morador@test.com')
        ->and($result->phone)->toBe('11999990000')
        ->and($result->roleInUnit)->toBe('owner')
        ->and($result->isPrimary)->toBeTrue()
        ->and($result->status)->toBe('invited')
        ->and($result->movedOutAt)->toBeNull();
});

test('throws UNIT_NOT_FOUND when unit does not exist', function () {
    $tenantContext = inviteResidentTenantContext();
    $unitId = Uuid::generate();

    $unitRepo = Mockery::mock(UnitRepositoryInterface::class);
    $unitRepo->shouldReceive('findById')->andReturnNull();

    $residentRepo = Mockery::mock(ResidentRepositoryInterface::class);
    $tenantUserRepo = Mockery::mock(TenantUserRepositoryInterface::class);
    $featureResolver = Mockery::mock(FeatureResolverInterface::class);
    $notificationService = Mockery::mock(NotificationServiceInterface::class);
    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new InviteResident(
        $unitRepo, $residentRepo, $tenantUserRepo,
        $featureResolver, $notificationService,
        $eventDispatcher, $tenantContext,
    );

    $dto = new InviteResidentDTO(
        unitId: $unitId->value(),
        name: 'Test',
        email: 'test@test.com',
        phone: null,
        document: null,
        roleInUnit: 'owner',
    );

    try {
        $useCase->execute($dto);
        $this->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('UNIT_NOT_FOUND')
            ->and($e->context())->toHaveKey('unit_id', $unitId->value());
    }
});

test('throws UNIT_INACTIVE for inactive unit', function () {
    $tenantContext = inviteResidentTenantContext();
    $unit = buildActiveUnit(Uuid::generate());
    $unit->deactivate();
    $unit->pullDomainEvents();

    $unitRepo = Mockery::mock(UnitRepositoryInterface::class);
    $unitRepo->shouldReceive('findById')->andReturn($unit);

    $residentRepo = Mockery::mock(ResidentRepositoryInterface::class);
    $tenantUserRepo = Mockery::mock(TenantUserRepositoryInterface::class);
    $featureResolver = Mockery::mock(FeatureResolverInterface::class);
    $notificationService = Mockery::mock(NotificationServiceInterface::class);
    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new InviteResident(
        $unitRepo, $residentRepo, $tenantUserRepo,
        $featureResolver, $notificationService,
        $eventDispatcher, $tenantContext,
    );

    $dto = new InviteResidentDTO(
        unitId: $unit->id()->value(),
        name: 'Test',
        email: 'test@test.com',
        phone: null,
        document: null,
        roleInUnit: 'owner',
    );

    try {
        $useCase->execute($dto);
        $this->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('UNIT_INACTIVE');
    }
});

test('throws USER_EMAIL_DUPLICATE when email exists', function () {
    $tenantContext = inviteResidentTenantContext();
    $unit = buildActiveUnit(Uuid::generate());

    $existingUser = new Domain\Auth\Entities\TenantUser(
        id: Uuid::generate(),
        email: 'existing@test.com',
        name: 'Existing',
        passwordHash: 'hash',
        role: Domain\Auth\Enums\TenantRole::Condomino,
        status: Domain\Auth\Enums\TenantUserStatus::Active,
    );

    $unitRepo = Mockery::mock(UnitRepositoryInterface::class);
    $unitRepo->shouldReceive('findById')->andReturn($unit);

    $residentRepo = Mockery::mock(ResidentRepositoryInterface::class);

    $tenantUserRepo = Mockery::mock(TenantUserRepositoryInterface::class);
    $tenantUserRepo->shouldReceive('findByEmail')->andReturn($existingUser);

    $featureResolver = Mockery::mock(FeatureResolverInterface::class);
    $notificationService = Mockery::mock(NotificationServiceInterface::class);
    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new InviteResident(
        $unitRepo, $residentRepo, $tenantUserRepo,
        $featureResolver, $notificationService,
        $eventDispatcher, $tenantContext,
    );

    $dto = new InviteResidentDTO(
        unitId: $unit->id()->value(),
        name: 'Test',
        email: 'existing@test.com',
        phone: null,
        document: null,
        roleInUnit: 'owner',
    );

    try {
        $useCase->execute($dto);
        $this->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('USER_EMAIL_DUPLICATE')
            ->and($e->context())->toHaveKey('email', 'existing@test.com');
    }
});

test('throws USER_LIMIT_REACHED when max_users exceeded', function () {
    $tenantContext = inviteResidentTenantContext();
    $unit = buildActiveUnit(Uuid::generate());

    $unitRepo = Mockery::mock(UnitRepositoryInterface::class);
    $unitRepo->shouldReceive('findById')->andReturn($unit);
    $unitRepo->shouldReceive('countByTenant')->andReturn(10);

    $residentRepo = Mockery::mock(ResidentRepositoryInterface::class);

    $tenantUserRepo = Mockery::mock(TenantUserRepositoryInterface::class);
    $tenantUserRepo->shouldReceive('findByEmail')->andReturnNull();

    $featureResolver = Mockery::mock(FeatureResolverInterface::class);
    $featureResolver->shouldReceive('featureLimit')
        ->with(Mockery::type(Uuid::class), 'max_users')
        ->andReturn(10);

    $notificationService = Mockery::mock(NotificationServiceInterface::class);
    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new InviteResident(
        $unitRepo, $residentRepo, $tenantUserRepo,
        $featureResolver, $notificationService,
        $eventDispatcher, $tenantContext,
    );

    $dto = new InviteResidentDTO(
        unitId: $unit->id()->value(),
        name: 'Test',
        email: 'new@test.com',
        phone: null,
        document: null,
        roleInUnit: 'owner',
    );

    try {
        $useCase->execute($dto);
        $this->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('USER_LIMIT_REACHED')
            ->and($e->context())->toHaveKey('max_users', 10);
    }
});

test('throws RESIDENT_LIMIT_REACHED when max_residents_per_unit exceeded', function () {
    $tenantContext = inviteResidentTenantContext();
    $unit = buildActiveUnit(Uuid::generate());

    $unitRepo = Mockery::mock(UnitRepositoryInterface::class);
    $unitRepo->shouldReceive('findById')->andReturn($unit);
    $unitRepo->shouldReceive('countByTenant')->andReturn(1);

    $residentRepo = Mockery::mock(ResidentRepositoryInterface::class);
    $residentRepo->shouldReceive('countActiveByUnitId')->andReturn(3);

    $tenantUserRepo = Mockery::mock(TenantUserRepositoryInterface::class);
    $tenantUserRepo->shouldReceive('findByEmail')->andReturnNull();

    $featureResolver = Mockery::mock(FeatureResolverInterface::class);
    $featureResolver->shouldReceive('featureLimit')
        ->with(Mockery::type(Uuid::class), 'max_users')
        ->andReturn(100);
    $featureResolver->shouldReceive('featureLimit')
        ->with(Mockery::type(Uuid::class), 'max_residents_per_unit')
        ->andReturn(3);

    $notificationService = Mockery::mock(NotificationServiceInterface::class);
    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new InviteResident(
        $unitRepo, $residentRepo, $tenantUserRepo,
        $featureResolver, $notificationService,
        $eventDispatcher, $tenantContext,
    );

    $dto = new InviteResidentDTO(
        unitId: $unit->id()->value(),
        name: 'Test',
        email: 'new@test.com',
        phone: null,
        document: null,
        roleInUnit: 'dependent',
    );

    try {
        $useCase->execute($dto);
        $this->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('RESIDENT_LIMIT_REACHED')
            ->and($e->context())->toHaveKey('max_residents_per_unit', 3)
            ->and($e->context())->toHaveKey('current_count', 3);
    }
});

test('notification sent with correct template and data', function () {
    $tenantContext = inviteResidentTenantContext();
    $unit = buildActiveUnit(Uuid::generate());

    $unitRepo = Mockery::mock(UnitRepositoryInterface::class);
    $unitRepo->shouldReceive('findById')->andReturn($unit);
    $unitRepo->shouldReceive('countByTenant')->andReturn(1);

    $residentRepo = Mockery::mock(ResidentRepositoryInterface::class);
    $residentRepo->shouldReceive('countActiveByUnitId')->andReturn(0);
    $residentRepo->shouldReceive('save');

    $tenantUserRepo = Mockery::mock(TenantUserRepositoryInterface::class);
    $tenantUserRepo->shouldReceive('findByEmail')->andReturnNull();
    $tenantUserRepo->shouldReceive('save');
    $tenantUserRepo->shouldReceive('saveInvitationToken');

    $featureResolver = Mockery::mock(FeatureResolverInterface::class);
    $featureResolver->shouldReceive('featureLimit')->andReturn(100);

    $notificationService = Mockery::mock(NotificationServiceInterface::class);
    $notificationService->shouldReceive('send')
        ->once()
        ->withArgs(function (string $channel, string $to, string $template, array $data) use ($tenantContext) {
            expect($channel)->toBe('email')
                ->and($to)->toBe('notif@test.com')
                ->and($template)->toBe('resident-invitation')
                ->and($data)->toHaveKey('name', 'Notificacao Teste')
                ->and($data)->toHaveKey('condominium_name', $tenantContext->tenantName)
                ->and($data)->toHaveKey('token')
                ->and($data)->toHaveKey('expires_at');

            return true;
        });

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatchAll');

    $useCase = new InviteResident(
        $unitRepo, $residentRepo, $tenantUserRepo,
        $featureResolver, $notificationService,
        $eventDispatcher, $tenantContext,
    );

    $dto = new InviteResidentDTO(
        unitId: $unit->id()->value(),
        name: 'Notificacao Teste',
        email: 'notif@test.com',
        phone: null,
        document: null,
        roleInUnit: 'tenant_resident',
    );

    $useCase->execute($dto);
});
