<?php

declare(strict_types=1);

namespace App\Providers;

use App\Infrastructure\Gateways\AI\PrismEmbeddingGenerator;
use App\Infrastructure\Gateways\AI\PrismTextGenerator;
use App\Infrastructure\Persistence\Tenant\Repositories\EloquentAIActionLogRepository;
use App\Infrastructure\Persistence\Tenant\Repositories\EloquentAIUsageLogRepository;
use App\Infrastructure\Persistence\Tenant\Repositories\EloquentEmbeddingRepository;
use Application\AI\Contracts\AIActionLogRepositoryInterface;
use Application\AI\Contracts\AIUsageLogRepositoryInterface;
use Application\AI\Contracts\EmbeddingGenerationInterface;
use Application\AI\Contracts\EmbeddingRepositoryInterface;
use Application\AI\Contracts\TextGenerationInterface;
use Application\AI\ToolRegistry;
use Illuminate\Support\ServiceProvider;

class AIServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TextGenerationInterface::class, PrismTextGenerator::class);
        $this->app->bind(EmbeddingGenerationInterface::class, PrismEmbeddingGenerator::class);
        $this->app->bind(EmbeddingRepositoryInterface::class, EloquentEmbeddingRepository::class);
        $this->app->bind(AIUsageLogRepositoryInterface::class, EloquentAIUsageLogRepository::class);
        $this->app->bind(AIActionLogRepositoryInterface::class, EloquentAIActionLogRepository::class);

        $this->app->singleton(ToolRegistry::class, function () {
            return new ToolRegistry();
        });
    }

    public function boot(): void
    {
        $this->registerTools();
    }

    private function registerTools(): void
    {
        /** @var ToolRegistry $registry */
        $registry = $this->app->make(ToolRegistry::class);

        $registry->register(
            name: 'list_available_slots',
            description: 'Lista horários disponíveis para reserva de um espaço comum em uma data específica',
            parameters: [
                ['name' => 'space_id', 'type' => 'string', 'description' => 'UUID do espaço comum', 'required' => true],
                ['name' => 'date', 'type' => 'string', 'description' => 'Data no formato YYYY-MM-DD', 'required' => true],
            ],
            handler: function (string $space_id, string $date): string {
                $useCase = app(\Application\Reservation\UseCases\ListAvailableSlots::class);
                $slots = $useCase->execute($space_id, $date);

                return json_encode($slots, JSON_THROW_ON_ERROR);
            },
        );

        $registry->register(
            name: 'list_reservations',
            description: 'Lista as reservas existentes, opcionalmente filtradas por espaço',
            parameters: [
                ['name' => 'space_id', 'type' => 'string', 'description' => 'UUID do espaço (opcional)', 'required' => false],
            ],
            handler: function (string $space_id = ''): string {
                $repository = app(\Application\Reservation\Contracts\ReservationRepositoryInterface::class);
                $reservations = $space_id !== ''
                    ? $repository->findBySpace(\Domain\Shared\ValueObjects\Uuid::fromString($space_id))
                    : [];

                return json_encode(array_map(fn ($r) => [
                    'id' => $r->id()->value(),
                    'space_id' => $r->spaceId()->value(),
                    'status' => $r->status()->value,
                    'start' => $r->dateRange()->start()->format('Y-m-d H:i'),
                    'end' => $r->dateRange()->end()->format('Y-m-d H:i'),
                ], $reservations), JSON_THROW_ON_ERROR);
            },
        );

        $registry->register(
            name: 'search_rules',
            description: 'Busca regras do condomínio por palavra-chave ou tema',
            parameters: [
                ['name' => 'query', 'type' => 'string', 'description' => 'Termo de busca', 'required' => true],
            ],
            handler: function (string $query): string {
                $repository = app(\Application\Governance\Contracts\CondominiumRuleRepositoryInterface::class);
                $rules = $repository->findAll();

                $filtered = array_filter($rules, fn ($rule) => str_contains(
                    mb_strtolower($rule->title() . ' ' . $rule->description()),
                    mb_strtolower($query),
                ));

                return json_encode(array_map(fn ($r) => [
                    'id' => $r->id()->value(),
                    'title' => $r->title(),
                    'description' => $r->description(),
                ], array_values($filtered)), JSON_THROW_ON_ERROR);
            },
        );

        $registry->register(
            name: 'list_announcements',
            description: 'Lista os avisos publicados do condomínio',
            parameters: [],
            handler: function (): string {
                $repository = app(\Application\Communication\Contracts\AnnouncementRepositoryInterface::class);
                $announcements = $repository->findAll();

                return json_encode(array_map(fn ($a) => [
                    'id' => $a->id()->value(),
                    'title' => $a->title(),
                    'body' => $a->body(),
                    'priority' => $a->priority()->value,
                    'status' => $a->status()->value,
                ], $announcements), JSON_THROW_ON_ERROR);
            },
        );

        $registry->register(
            name: 'create_reservation',
            description: 'Cria uma nova reserva para um espaço comum (requer confirmação do usuário)',
            parameters: [
                ['name' => 'space_id', 'type' => 'string', 'description' => 'UUID do espaço', 'required' => true],
                ['name' => 'start_datetime', 'type' => 'string', 'description' => 'Início da reserva (ISO 8601)', 'required' => true],
                ['name' => 'end_datetime', 'type' => 'string', 'description' => 'Fim da reserva (ISO 8601)', 'required' => true],
            ],
            handler: function (string $space_id, string $start_datetime, string $end_datetime): string {
                return json_encode([
                    'space_id' => $space_id,
                    'start_datetime' => $start_datetime,
                    'end_datetime' => $end_datetime,
                    'status' => 'requires_user_context',
                ], JSON_THROW_ON_ERROR);
            },
            requiresConfirmation: true,
        );
    }
}
