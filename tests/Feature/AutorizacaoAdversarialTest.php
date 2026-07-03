<?php

use App\Actions\AprovarEtapaAction;
use App\Enums\NivelAlcada;
use App\Enums\Perfil;
use App\Enums\StatusAprovacao;
use App\Enums\StatusPagamento;
use App\Enums\StatusRequisicao;
use App\Livewire\Aprovacoes\PainelAprovacao;
use App\Livewire\Financeiro\ListaPagamentos;
use App\Models\Aprovacao;
use App\Models\CentroCusto;
use App\Models\FaixaAlcada;
use App\Models\ItemRequisicao;
use App\Models\Pagamento;
use App\Models\Requisicao;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Testes adversários de autorização / isolamento por unidade.
|--------------------------------------------------------------------------
|
| Assertam as GARANTIAS de segurança que o design promete (UnidadeScope
| fail-closed, nível de alçada, anti-IDOR na aprovação, gate de papel no
| Financeiro). Se qualquer uma regredir, estes testes falham em vez de a
| falha vazar para produção. O isolamento cross-unidade de Requisicao já é
| coberto em Fase2Test; aqui focamos os caminhos NEGATIVOS ainda descobertos.
|
*/

uses(RefreshDatabase::class);

/**
 * Requisição em AguardandoAprovacao numa unidade, com UMA etapa pendente do nível dado.
 *
 * @return array{0: Requisicao, 1: Unidade, 2: User} [requisição, unidade, solicitante]
 */
function advCenario(NivelAlcada $nivelEtapa): array
{
    $unidade = Unidade::factory()->create();
    $solicitante = User::factory()->create();
    $solicitante->unidades()->attach($unidade->id, ['perfil' => Perfil::Solicitante->value]);
    $centro = CentroCusto::factory()->create(['unidade_id' => $unidade->id]);
    $faixa = FaixaAlcada::factory()->create([
        'valor_minimo' => 0, 'valor_maximo' => null, 'is_emergencial' => false, 'ativo' => true,
    ]);

    $req = Requisicao::factory()->create([
        'unidade_id' => $unidade->id,
        'solicitante_id' => $solicitante->id,
        'centro_custo_id' => $centro->id,
        'status' => StatusRequisicao::AguardandoAprovacao,
        'faixa_alcada_id' => $faixa->id,
        'ciclo_aprovacao' => 1,
        'codigo' => 'REQ-ADV-'.fake()->unique()->numerify('######'),
    ]);
    ItemRequisicao::factory()->create([
        'requisicao_id' => $req->id,
        'item_catalogo_id' => null,
        'avulso' => true,
        'quantidade' => 1,
        'valor_unitario_estimado' => 100,
    ]);
    Aprovacao::create([
        'requisicao_id' => $req->id,
        'etapa_alcada_id' => null,
        'ciclo' => 1,
        'ordem' => 1,
        'nivel_exigido' => $nivelEtapa->value,
        'obrigatoria_emergencial' => false,
        'status' => StatusAprovacao::Pendente->value,
    ]);

    return [$req, $unidade, $solicitante];
}

function advAprovador(Unidade $unidade, NivelAlcada $nivel): User
{
    $u = User::factory()->create();
    $u->unidades()->attach($unidade->id, ['perfil' => Perfil::Aprovador->value, 'nivel_alcada' => $nivel->value]);

    return $u;
}

// ─── Nível de alçada ─────────────────────────────────────────────────────────

it('bloqueia aprovador de nível inferior ao exigido pela etapa', function () {
    Mail::fake();
    [$req, $unidade] = advCenario(NivelAlcada::Diretor);
    $gestor = advAprovador($unidade, NivelAlcada::Gestor); // nível insuficiente

    expect(fn () => app(AprovarEtapaAction::class)->execute($req, $gestor, 'ok'))
        ->toThrow(ValidationException::class);

    expect(Requisicao::withoutGlobalScopes()->find($req->id)->status)
        ->toBe(StatusRequisicao::AguardandoAprovacao);
});

// ─── Anti-IDOR: unidade errada ───────────────────────────────────────────────

it('bloqueia aprovador de outra unidade no painel de aprovação (anti-IDOR)', function () {
    [$reqA] = advCenario(NivelAlcada::Gestor);
    $outraUnidade = Unidade::factory()->create();
    $aprovadorB = advAprovador($outraUnidade, NivelAlcada::Gestor); // é aprovador, mas de OUTRA unidade

    Livewire::actingAs($aprovadorB)
        ->test(PainelAprovacao::class, ['id' => $reqA->id])
        ->assertForbidden();
});

// ─── Auto-aprovação ──────────────────────────────────────────────────────────

it('impede o solicitante de aprovar a própria requisição mesmo com papel de aprovador', function () {
    Mail::fake();
    $unidade = Unidade::factory()->create();
    $centro = CentroCusto::factory()->create(['unidade_id' => $unidade->id]);
    $faixa = FaixaAlcada::factory()->create(['valor_minimo' => 0, 'valor_maximo' => null, 'is_emergencial' => false, 'ativo' => true]);

    // O usuário é aprovador do nível certo na unidade — e também é o solicitante.
    $usuario = User::factory()->create();
    $usuario->unidades()->attach($unidade->id, ['perfil' => Perfil::Aprovador->value, 'nivel_alcada' => NivelAlcada::Gestor->value]);

    $req = Requisicao::factory()->create([
        'unidade_id' => $unidade->id,
        'solicitante_id' => $usuario->id,
        'centro_custo_id' => $centro->id,
        'status' => StatusRequisicao::AguardandoAprovacao,
        'faixa_alcada_id' => $faixa->id,
        'ciclo_aprovacao' => 1,
        'codigo' => 'REQ-ADV-'.fake()->unique()->numerify('######'),
    ]);
    ItemRequisicao::factory()->create([
        'requisicao_id' => $req->id, 'item_catalogo_id' => null, 'avulso' => true,
        'quantidade' => 1, 'valor_unitario_estimado' => 100,
    ]);
    Aprovacao::create([
        'requisicao_id' => $req->id, 'etapa_alcada_id' => null, 'ciclo' => 1, 'ordem' => 1,
        'nivel_exigido' => NivelAlcada::Gestor->value, 'obrigatoria_emergencial' => false,
        'status' => StatusAprovacao::Pendente->value,
    ]);

    expect(fn () => app(AprovarEtapaAction::class)->execute($req, $usuario, 'ok'))
        ->toThrow(ValidationException::class);

    expect(Requisicao::withoutGlobalScopes()->find($req->id)->status)
        ->toBe(StatusRequisicao::AguardandoAprovacao);
});

// ─── UnidadeScope fail-closed ────────────────────────────────────────────────

it('usuário sem vínculo de unidade não enxerga nenhuma requisição (fail-closed)', function () {
    advCenario(NivelAlcada::Gestor); // existe 1 requisição no banco
    $semVinculo = User::factory()->create(); // sem unidade_user, sem admin, sem papel compras

    $this->actingAs($semVinculo);

    expect(Requisicao::count())->toBe(0);
});

// ─── Gate de papel no Financeiro ─────────────────────────────────────────────

it('tela de pagamentos responde 403 para quem não é financeiro nem admin', function () {
    // A autorização de pagamentos vive na SUPERFÍCIE Livewire: mount exige
    // podeVerPagamentos e cada ação (registrar/agendar/cancelar) exige
    // podeGerenciarPagamentos (abort_unless). Como o mount aborta 403, o não-autorizado
    // sequer alcança as ações. As Actions em si não checam authz — só invariantes de
    // negócio (lock/status) — conforme o padrão atual do projeto (sem Policies).
    $qualquer = User::factory()->create();

    Livewire::actingAs($qualquer)
        ->test(ListaPagamentos::class)
        ->assertForbidden();
});

it('compradora sênior (sem papel financeiro) não acessa pagamentos', function () {
    // podeVerPagamentos = financeiro || admin. Compradora tem o papel `compras`,
    // não `financeiro` — logo é barrada já no mount do componente.
    $compradora = User::factory()->compradora()->create();

    Livewire::actingAs($compradora)
        ->test(ListaPagamentos::class)
        ->assertForbidden();
});

it('admin e financeiro conseguem registrar pagamento', function () {
    foreach ([User::factory()->admin()->create(), User::factory()->financeiro()->create()] as $usuario) {
        $pag = Pagamento::factory()->create(['valor_total' => 500, 'status' => StatusPagamento::Pendente]);

        Livewire::actingAs($usuario)
            ->test(ListaPagamentos::class)
            ->assertOk()
            ->call('abrirRegistrar', $pag->id)
            ->set('metodo', 'transferencia')
            ->call('registrar')
            ->assertHasNoErrors();

        expect($pag->fresh()->status)->toBe(StatusPagamento::Pago);
    }
});
