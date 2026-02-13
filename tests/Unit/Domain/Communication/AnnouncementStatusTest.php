<?php

declare(strict_types=1);

use Domain\Communication\Enums\AnnouncementStatus;

test('has correct cases', function () {
    expect(AnnouncementStatus::cases())->toHaveCount(3);
    expect(AnnouncementStatus::Draft->value)->toBe('draft');
    expect(AnnouncementStatus::Published->value)->toBe('published');
    expect(AnnouncementStatus::Archived->value)->toBe('archived');
});

test('draft can transition to published', function () {
    expect(AnnouncementStatus::Draft->canTransitionTo(AnnouncementStatus::Published))->toBeTrue();
});

test('published can transition to archived', function () {
    expect(AnnouncementStatus::Published->canTransitionTo(AnnouncementStatus::Archived))->toBeTrue();
});

test('draft cannot transition to archived', function () {
    expect(AnnouncementStatus::Draft->canTransitionTo(AnnouncementStatus::Archived))->toBeFalse();
});

test('published cannot transition to draft', function () {
    expect(AnnouncementStatus::Published->canTransitionTo(AnnouncementStatus::Draft))->toBeFalse();
});

test('archived cannot transition to any state', function () {
    expect(AnnouncementStatus::Archived->canTransitionTo(AnnouncementStatus::Draft))->toBeFalse();
    expect(AnnouncementStatus::Archived->canTransitionTo(AnnouncementStatus::Published))->toBeFalse();
});

test('archived is terminal', function () {
    expect(AnnouncementStatus::Archived->isTerminal())->toBeTrue();
});

test('draft and published are not terminal', function () {
    expect(AnnouncementStatus::Draft->isTerminal())->toBeFalse();
    expect(AnnouncementStatus::Published->isTerminal())->toBeFalse();
});

test('published is visible', function () {
    expect(AnnouncementStatus::Published->isVisible())->toBeTrue();
});

test('draft and archived are not visible', function () {
    expect(AnnouncementStatus::Draft->isVisible())->toBeFalse();
    expect(AnnouncementStatus::Archived->isVisible())->toBeFalse();
});

test('labels are correct', function () {
    expect(AnnouncementStatus::Draft->label())->toBe('Rascunho');
    expect(AnnouncementStatus::Published->label())->toBe('Publicado');
    expect(AnnouncementStatus::Archived->label())->toBe('Arquivado');
});

test('allowed transitions are correct', function () {
    expect(AnnouncementStatus::Draft->allowedTransitions())->toBe([AnnouncementStatus::Published]);
    expect(AnnouncementStatus::Published->allowedTransitions())->toBe([AnnouncementStatus::Archived]);
    expect(AnnouncementStatus::Archived->allowedTransitions())->toBe([]);
});
