<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use App\Infrastructure\Persistence\Tenant\Models\BlockModel;
use App\Infrastructure\Persistence\Tenant\Models\CondominiumRuleModel;
use App\Infrastructure\Persistence\Tenant\Models\PenaltyPolicyModel;
use App\Infrastructure\Persistence\Tenant\Models\ReservationModel;
use App\Infrastructure\Persistence\Tenant\Models\ResidentModel;
use App\Infrastructure\Persistence\Tenant\Models\SpaceAvailabilityModel;
use App\Infrastructure\Persistence\Tenant\Models\SpaceModel;
use App\Infrastructure\Persistence\Tenant\Models\SpaceRuleModel;
use App\Infrastructure\Persistence\Tenant\Models\TenantUserModel;
use App\Infrastructure\Persistence\Tenant\Models\UnitModel;
use App\Infrastructure\Persistence\Tenant\Models\ViolationModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        // ── Tenant Users ────────────────────────────────────────
        $sindico = TenantUserModel::query()->updateOrCreate(
            ['email' => 'sindico@condominio.com.br'],
            [
                'id' => Str::uuid()->toString(),
                'name' => 'João Silva (Síndico)',
                'password_hash' => Hash::make('SenhaSegura123'),
                'phone' => '11999990001',
                'role' => 'sindico',
                'status' => 'active',
                'mfa_enabled' => false,
            ],
        );

        $morador = TenantUserModel::query()->updateOrCreate(
            ['email' => 'morador@email.com'],
            [
                'id' => Str::uuid()->toString(),
                'name' => 'Maria Santos',
                'password_hash' => Hash::make('SenhaSegura123'),
                'phone' => '11999990002',
                'role' => 'condomino',
                'status' => 'active',
                'mfa_enabled' => false,
            ],
        );

        // ── Block ───────────────────────────────────────────────
        $blocoA = BlockModel::query()->updateOrCreate(
            ['identifier' => 'A'],
            [
                'id' => Str::uuid()->toString(),
                'name' => 'Bloco A',
                'floors' => 10,
                'status' => 'active',
            ],
        );

        // ── Units ───────────────────────────────────────────────
        $unit101 = UnitModel::query()->updateOrCreate(
            ['block_id' => $blocoA->id, 'number' => '101'],
            [
                'id' => Str::uuid()->toString(),
                'floor' => 1,
                'type' => 'apartment',
                'status' => 'active',
                'is_occupied' => true,
            ],
        );

        UnitModel::query()->updateOrCreate(
            ['block_id' => $blocoA->id, 'number' => '102'],
            [
                'id' => Str::uuid()->toString(),
                'floor' => 1,
                'type' => 'apartment',
                'status' => 'active',
                'is_occupied' => false,
            ],
        );

        // ── Resident ────────────────────────────────────────────
        $resident = ResidentModel::query()->updateOrCreate(
            ['unit_id' => $unit101->id, 'tenant_user_id' => $morador->id],
            [
                'id' => Str::uuid()->toString(),
                'role_in_unit' => 'owner',
                'is_primary' => true,
                'status' => 'active',
                'moved_in_at' => now()->subMonths(6),
            ],
        );

        // ── Spaces ──────────────────────────────────────────────
        $salao = SpaceModel::query()->updateOrCreate(
            ['name' => 'Salão de Festas'],
            [
                'id' => Str::uuid()->toString(),
                'description' => 'Salão com capacidade para 100 pessoas, cozinha e banheiros',
                'type' => 'party_hall',
                'status' => 'active',
                'capacity' => 100,
                'requires_approval' => true,
                'max_duration_hours' => 12,
                'max_advance_days' => 30,
                'min_advance_hours' => 48,
                'cancellation_deadline_hours' => 24,
            ],
        );

        $piscina = SpaceModel::query()->updateOrCreate(
            ['name' => 'Piscina'],
            [
                'id' => Str::uuid()->toString(),
                'description' => 'Piscina adulto e infantil com deck',
                'type' => 'pool',
                'status' => 'active',
                'capacity' => 30,
                'requires_approval' => false,
                'max_duration_hours' => 4,
                'max_advance_days' => 15,
                'min_advance_hours' => 24,
                'cancellation_deadline_hours' => 12,
            ],
        );

        // ── Space Availability (Seg-Sex 08:00-22:00) ────────────
        foreach ([$salao, $piscina] as $space) {
            for ($day = 1; $day <= 5; $day++) {
                SpaceAvailabilityModel::query()->updateOrCreate(
                    ['space_id' => $space->id, 'day_of_week' => $day, 'start_time' => '08:00'],
                    [
                        'id' => Str::uuid()->toString(),
                        'end_time' => '22:00',
                    ],
                );
            }

            // Sabado 09:00-18:00
            SpaceAvailabilityModel::query()->updateOrCreate(
                ['space_id' => $space->id, 'day_of_week' => 6, 'start_time' => '09:00'],
                [
                    'id' => Str::uuid()->toString(),
                    'end_time' => '18:00',
                ],
            );
        }

        // ── Space Rules (Salao) ─────────────────────────────────
        $rules = [
            ['key' => 'max_sound_db', 'value' => '80', 'desc' => 'Limite de som em decibéis'],
            ['key' => 'max_guests', 'value' => '100', 'desc' => 'Máximo de convidados permitidos'],
            ['key' => 'cleaning_fee_brl', 'value' => '150.00', 'desc' => 'Taxa de limpeza em reais'],
        ];

        foreach ($rules as $rule) {
            SpaceRuleModel::query()->updateOrCreate(
                ['space_id' => $salao->id, 'rule_key' => $rule['key']],
                [
                    'id' => Str::uuid()->toString(),
                    'rule_value' => $rule['value'],
                    'description' => $rule['desc'],
                ],
            );
        }

        // ── Reservations ──────────────────────────────────────────
        ReservationModel::query()->updateOrCreate(
            ['space_id' => $salao->id, 'unit_id' => $unit101->id, 'title' => 'Churrasco de Aniversário'],
            [
                'id' => Str::uuid()->toString(),
                'resident_id' => $resident->id,
                'status' => 'confirmed',
                'start_datetime' => now()->addDays(3)->setTime(14, 0),
                'end_datetime' => now()->addDays(3)->setTime(22, 0),
                'expected_guests' => 30,
                'notes' => 'Aniversário de 30 anos. Levar decoração às 12h.',
                'approved_by' => $sindico->id,
                'approved_at' => now()->subDay(),
            ],
        );

        ReservationModel::query()->updateOrCreate(
            ['space_id' => $piscina->id, 'unit_id' => $unit101->id, 'title' => 'Tarde na Piscina'],
            [
                'id' => Str::uuid()->toString(),
                'resident_id' => $resident->id,
                'status' => 'pending_approval',
                'start_datetime' => now()->addDays(7)->setTime(10, 0),
                'end_datetime' => now()->addDays(7)->setTime(14, 0),
                'expected_guests' => 8,
                'notes' => null,
            ],
        );

        // ── Condominium Rules ────────────────────────────────────
        CondominiumRuleModel::query()->updateOrCreate(
            ['title' => 'Limite de barulho'],
            [
                'id' => Str::uuid()->toString(),
                'description' => 'Proibido barulho excessivo (acima de 80dB) apos as 22h e antes das 8h em dias uteis, e apos as 23h nos fins de semana.',
                'category' => 'noise',
                'is_active' => true,
                'order' => 1,
                'created_by' => $sindico->id,
            ],
        );

        $silencioRule = CondominiumRuleModel::query()->updateOrCreate(
            ['title' => 'Horario de silencio'],
            [
                'id' => Str::uuid()->toString(),
                'description' => 'Silencio absoluto entre 22h e 8h em todas as areas comuns do condominio.',
                'category' => 'noise',
                'is_active' => true,
                'order' => 2,
                'created_by' => $sindico->id,
            ],
        );

        // ── Violation (open, no_show) ─────────────────────────────
        ViolationModel::query()->updateOrCreate(
            ['unit_id' => $unit101->id, 'type' => 'no_show', 'description' => 'Morador nao compareceu a reserva do Salao de Festas.'],
            [
                'id' => Str::uuid()->toString(),
                'tenant_user_id' => $morador->id,
                'rule_id' => $silencioRule->id,
                'severity' => 'low',
                'status' => 'open',
                'is_automatic' => true,
                'created_at' => now()->subDays(2),
            ],
        );

        // ── Penalty Policy ────────────────────────────────────────
        PenaltyPolicyModel::query()->updateOrCreate(
            ['violation_type' => 'no_show', 'occurrence_threshold' => 2],
            [
                'id' => Str::uuid()->toString(),
                'penalty_type' => 'temporary_block',
                'block_days' => 15,
                'is_active' => true,
            ],
        );

        $this->command->info('  Tenant seeded: users, block, units, resident, spaces, availability, rules, reservations, governance');
    }
}
