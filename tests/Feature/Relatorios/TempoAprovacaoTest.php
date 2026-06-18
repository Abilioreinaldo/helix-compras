<?php

use App\Enums\StatusRequisicao;
use App\Livewire\Relatorios\TempoAprovacao;
use App\Models\FaixaAlcada;
use App\Models\Requisicao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(fn () => Mail::fake());

// ─── Helpers ─────────────────────────────────────────────────────────────────

function r2_faixa(string $nome = 'Faixa R2'): FaixaAlcada
{
    return FaixaAlcada::factory()->create([
        'nome' => $nome,
        'valor_minimo' => 0,
    ]);
}

function r2_aprovada(FaixaAlcada $faixa, int $horasCiclo = 2): Requisicao
{
    return Requisicao::factory()->create([
        'status' => StatusRequisicao::Aprovada,
        'faixa_alcada_id' => $faixa->id,
        'codigo' => 'REQ-2026-'.fake()->unique()->numerify('######'),
        'submetida_em' => now()->subHours($horasCiclo + 1),
        'aprovacao_iniciada_em' => now()->subHours($horasCiclo),
        'aprovada_em' => now(),
        'ciclo_aprovacao' => 1,
    ]);
}

// ─── Autorização ───────────────────────────────────────────────────────────────

it('rota_tempo_aprovacao_retorna_403_para_usuario_sem_permissao', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('relatorios.tempo-aprovacao'))
        ->assertForbidden();
});

it('rota_tempo_aprovacao_retorna_200_para_compradora', function () {
    $this->actingAs(User::factory()->compradora()->create())
        ->get(route('relatorios.tempo-aprovacao'))
        ->assertSuccessful();
});

// ─── Estado vazio ────────────────────────────────────────────────────────────

it('tempo_aprovacao_sem_dados_renderiza_mensagem_vazia', function () {
    $this->actingAs(User::factory()->compradora()->create())
        ->get(route('relatorios.tempo-aprovacao'))
        ->assertSuccessful()
        ->assertSee('Nenhuma aprovação concluída');
});

// ─── Agregação ─────────────────────────────────────────────────────────────────

it('tempo_aprovacao_calcula_media_do_ciclo_por_faixa', function () {
    $compradora = User::factory()->compradora()->create();
    $faixa = r2_faixa('Faixa Gestor');
    r2_aprovada($faixa, horasCiclo: 2);

    $this->actingAs($compradora)
        ->get(route('relatorios.tempo-aprovacao'))
        ->assertSuccessful()
        ->assertSee('Faixa Gestor')
        ->assertSee('2,0 h');
});

// ─── Adversário: ciclo incompleto não entra na média ────────────────────────────

it('tempo_aprovacao_ignora_requisicao_sem_aprovada_em', function () {
    $compradora = User::factory()->compradora()->create();
    $faixa = r2_faixa('Faixa Mista');

    // Ciclo completo (deve contar).
    r2_aprovada($faixa, horasCiclo: 2);

    // Ciclo aberto: iniciada mas ainda não aprovada (NÃO deve contar nem dividir por nulo).
    Requisicao::factory()->create([
        'status' => StatusRequisicao::AguardandoAprovacao,
        'faixa_alcada_id' => $faixa->id,
        'codigo' => 'REQ-2026-'.fake()->unique()->numerify('######'),
        'submetida_em' => now()->subHours(5),
        'aprovacao_iniciada_em' => now()->subHours(4),
        'aprovada_em' => null,
        'ciclo_aprovacao' => 1,
    ]);

    $resultados = Livewire::actingAs($compradora)
        ->test(TempoAprovacao::class)
        ->viewData('resultados');

    expect($resultados)->toHaveCount(1);
    expect((int) $resultados->first()->total_requisicoes)->toBe(1);
    expect(round((float) $resultados->first()->horas_media, 1))->toBe(2.0);
});
