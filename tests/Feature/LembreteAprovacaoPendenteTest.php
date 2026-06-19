<?php

use App\Enums\Perfil;
use App\Enums\StatusAprovacao;
use App\Mail\LembreteAprovacaoPendente;
use App\Models\Aprovacao;
use App\Models\CentroCusto;
use App\Models\Requisicao;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

/**
 * Cria uma requisição num status com a etapa de aprovação pendente e um aprovador do nível.
 *
 * @return array{unidade: Unidade, requisicao: Requisicao, aprovador: User}
 */
function lap_setup(float $horasPendente = 50, string $nivel = 'gestor', string $status = 'aguardando_aprovacao'): array
{
    $unidade = Unidade::factory()->create();
    $solicitante = User::factory()->create();
    $centro = CentroCusto::factory()->create(['unidade_id' => $unidade->id]);

    $requisicao = Requisicao::create([
        'solicitante_id' => $solicitante->id,
        'unidade_id' => $unidade->id,
        'centro_custo_id' => $centro->id,
        'status' => $status,
        'urgente' => false,
        'is_emergencial' => false,
        'codigo' => 'REQ-LAP-'.fake()->unique()->numerify('#####'),
        'submetida_em' => now()->subHours($horasPendente + 4),
        'ciclo_aprovacao' => 1,
        'aprovacao_iniciada_em' => now()->subHours($horasPendente),
    ]);

    Aprovacao::create([
        'requisicao_id' => $requisicao->id,
        'ciclo' => 1,
        'ordem' => 1,
        'nivel_exigido' => $nivel,
        'obrigatoria_emergencial' => false,
        'status' => StatusAprovacao::Pendente->value,
    ]);

    $aprovador = User::factory()->create();
    $aprovador->unidades()->attach($unidade->id, ['perfil' => Perfil::Aprovador->value, 'nivel_alcada' => $nivel]);

    return compact('unidade', 'requisicao', 'aprovador');
}

it('lembra_aprovadores_de_pendencia_com_mais_de_48h', function () {
    Mail::fake();
    $s = lap_setup(horasPendente: 50);

    $this->artisan('aprovacoes:lembrar-pendentes')->assertExitCode(0);

    Mail::assertSent(LembreteAprovacaoPendente::class, 1);
    Mail::assertSent(LembreteAprovacaoPendente::class, fn ($m) => $m->hasTo($s['aprovador']->email)
        && $m->requisicao->is($s['requisicao']));
});

it('nao_lembra_pendencia_com_menos_de_48h', function () {
    Mail::fake();
    lap_setup(horasPendente: 10);

    $this->artisan('aprovacoes:lembrar-pendentes')->assertExitCode(0);

    Mail::assertNothingSent();
});

it('nao_lembra_requisicao_que_nao_esta_aguardando_aprovacao', function () {
    Mail::fake();
    lap_setup(horasPendente: 72, status: 'aprovada');

    $this->artisan('aprovacoes:lembrar-pendentes');

    Mail::assertNothingSent();
});

it('lembra_apenas_aprovadores_do_nivel_da_etapa_pendente', function () {
    Mail::fake();
    $s = lap_setup(horasPendente: 50, nivel: 'gestor');

    // Aprovador de OUTRO nível na mesma unidade não deve ser lembrado.
    $diretor = User::factory()->create();
    $diretor->unidades()->attach($s['unidade']->id, ['perfil' => Perfil::Aprovador->value, 'nivel_alcada' => 'diretor']);

    $this->artisan('aprovacoes:lembrar-pendentes');

    Mail::assertSent(LembreteAprovacaoPendente::class, 1);
    Mail::assertSent(LembreteAprovacaoPendente::class, fn ($m) => $m->hasTo($s['aprovador']->email));
    Mail::assertNotSent(LembreteAprovacaoPendente::class, fn ($m) => $m->hasTo($diretor->email));
});

it('nao_envia_nada_quando_nao_ha_etapa_pendente', function () {
    Mail::fake();
    $s = lap_setup(horasPendente: 50);
    // Resolve a etapa: nenhuma pendente sobra.
    Aprovacao::where('requisicao_id', $s['requisicao']->id)->update(['status' => StatusAprovacao::Aprovada->value]);

    $this->artisan('aprovacoes:lembrar-pendentes');

    Mail::assertNothingSent();
});
