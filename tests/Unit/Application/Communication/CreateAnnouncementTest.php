<?php

declare(strict_types=1);

use Application\Communication\Contracts\AnnouncementRepositoryInterface;
use Application\Communication\DTOs\AnnouncementDTO;
use Application\Communication\DTOs\CreateAnnouncementDTO;
use Application\Communication\UseCases\CreateAnnouncement;
use Application\Shared\Contracts\EventDispatcherInterface;
use Domain\Shared\ValueObjects\Uuid;

afterEach(fn () => Mockery::close());

function makeCreateAnnouncementDTO(): CreateAnnouncementDTO
{
    return new CreateAnnouncementDTO(
        title: 'Manutenção da piscina',
        body: 'A piscina ficará fechada para manutenção.',
        priority: 'high',
        audienceType: 'all',
        audienceIds: null,
        publishedBy: Uuid::generate()->value(),
        expiresAt: null,
    );
}

test('creates announcement with published status', function () {
    $dto = makeCreateAnnouncementDTO();

    $repo = Mockery::mock(AnnouncementRepositoryInterface::class);
    $repo->shouldReceive('save')->once();

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatchAll')->once();

    $useCase = new CreateAnnouncement($repo, $eventDispatcher);
    $result = $useCase->execute($dto);

    expect($result)->toBeInstanceOf(AnnouncementDTO::class)
        ->and($result->title)->toBe('Manutenção da piscina')
        ->and($result->priority)->toBe('high')
        ->and($result->audienceType)->toBe('all')
        ->and($result->status)->toBe('published')
        ->and($result->audienceIds)->toBeNull();
});

test('creates announcement with audience ids', function () {
    $blockId = Uuid::generate()->value();

    $dto = new CreateAnnouncementDTO(
        title: 'Aviso do bloco',
        body: 'Corpo.',
        priority: 'normal',
        audienceType: 'block',
        audienceIds: [$blockId],
        publishedBy: Uuid::generate()->value(),
        expiresAt: (new DateTimeImmutable('+7 days'))->format('c'),
    );

    $repo = Mockery::mock(AnnouncementRepositoryInterface::class);
    $repo->shouldReceive('save')->once();

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatchAll')->once();

    $useCase = new CreateAnnouncement($repo, $eventDispatcher);
    $result = $useCase->execute($dto);

    expect($result->audienceType)->toBe('block')
        ->and($result->audienceIds)->toBe([$blockId])
        ->and($result->expiresAt)->not->toBeNull();
});
