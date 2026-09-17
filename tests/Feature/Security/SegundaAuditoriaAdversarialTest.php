<?php

use App\Actions\ReprovarRequisicaoAction;
use App\Enums\NivelAlcada;
use App\Enums\Perfil;
use App\Enums\StatusAprovacao;
use App\Enums\StatusRequisicao;
use App\Livewire\Admin\Unidades\ListaUnidades;
use App\Livewire\Admin\Usuarios\ListaUsuarios;
use App\Livewire\Relatorios\ComprasEmergenciais;
use App\Mail\RequisicaoReprovada;
use App\Models\Aprovacao;
use App\Models\CentroCusto;
use App\Models\ItemRequisicao;
use App\Models\Requisicao;
use App\Models\Unidade;
use App\Models\User;
use Helix\Foundation\Models\Platform\Identity\Tenant;
use Helix\Foundation\Services\Platform\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| 2ª auditoria adversarial — achados do Compras (nota 7,0).
|--------------------------------------------------------------------------
|
| Cada teste aqui FALHA sem a respectiva correção. Tenant A é a vítima, B é o
| atacante (ou o vizinho que paga a conta). Padrão do repo: opt-out do contexto
| canônico (TenantContext::forget) e semeadura explícita por tenant.
|
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();

    $this->tenantA = Tenant::create(['slug' => 'alfa-2a', 'name' => 'Alfa', 'status' => 'active']);
    $this->tenantB = Tenant::create(['slug' => 'bravo-2a', 'name' => 'Bravo', 'status' => 'active']);

    foreach ([$this->tenantA, $this->tenantB] as $tenant) {
        $tenant->features()->firstOrCreate(['feature' => 'compras'], ['enabled' => true]);
    }

    TenantContext::runFor($this->tenantA->id, function () {
        $this->adminA = User::factory()->admin()->create(['tenant_id' => $this->tenantA->id, 'name' => 'Admin Alfa']);
        $this->unidadeA = Unidade::factory()->create(['tenant_id' => $this->tenantA->id, 'nome' => 'Obra Alfa Norte']);
    });

    TenantContext::runFor($this->tenantB->id, function () {
        $this->unidadeB = Unidade::factory()->create(['tenant_id' => $this->tenantB->id, 'nome' => 'Posto Bravo Centro']);
    });

    TenantContext::forget();
});

/**
 * Usuário com identidade (home) no tenant `$home` e vínculo ATIVO no tenant
 * `$convidadoEm`. O vínculo com a casa dele fica INATIVO — é o recorte exato do
 * achado: `temOutroVinculo()` (que só olha vínculos ativos) devolvia false.
 */
function usr2a_convidado(string $home, string $convidadoEm, string $nome): User
{
    $usuario = TenantContext::runFor($home, fn () => User::factory()->create(['tenant_id' => $home, 'name' => $nome]));

    DB::table('tenant_user')->insert([
        'user_id' => $usuario->getKey(),
        'tenant_id' => $convidadoEm,
        'is_admin' => false,
        'status' => 'active',
        'access_scope' => 'corporate',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('tenant_user')
        ->where('user_id', $usuario->getKey())
        ->where('tenant_id', $home)
        ->update(['status' => 'inactive']);

    return $usuario;
}

// ─── Achado 7: exclusão de identidade compartilhada pelo admin da outra empresa ─

it('o admin de A não soft-deleta a IDENTIDADE de quem tem home em B — só o vínculo', function () {
    // Antes: `temOutroVinculo()` era a única guarda e olha só vínculos ATIVOS. Com o
    // vínculo do convidado com a casa dele inativo, o admin de A caía no deleteUser e
    // apagava uma identidade de outra empresa — com a trilha nascendo no tenant HOME.
    $convidado = usr2a_convidado($this->tenantB->id, $this->tenantA->id, 'Convidado do Bravo');

    Livewire::actingAs($this->adminA)
        ->test(ListaUsuarios::class)
        ->call('excluir', $convidado->getKey());

    $identidade = User::withTrashed()->find($convidado->getKey());

    expect($identidade)->not->toBeNull()
        ->and($identidade->deleted_at)->toBeNull()
        // e o vínculo com A caiu (que é o que o admin de A pode mesmo desfazer)
        ->and(DB::table('tenant_user')
            ->where('user_id', $convidado->getKey())
            ->where('tenant_id', $this->tenantA->id)
            ->exists())->toBeFalse();
});

// ─── Achado 9: relatório de compras emergenciais ───────────────────────────────

/** Requisição emergencial no tenant informado, com um item estimado em `$valor`. */
function req2a_emergencial(string $tenantId, int $unidadeId, float $valor, string $codigo): Requisicao
{
    return TenantContext::runFor($tenantId, function () use ($unidadeId, $valor, $codigo, $tenantId) {
        $solicitante = User::factory()->create(['tenant_id' => $tenantId]);
        $centro = CentroCusto::factory()->create(['unidade_id' => $unidadeId, 'ativo' => true]);

        $requisicao = Requisicao::factory()->create([
            'unidade_id' => $unidadeId,
            'centro_custo_id' => $centro->id,
            'solicitante_id' => $solicitante->id,
            'status' => StatusRequisicao::EmCotacao,
            'is_emergencial' => true,
            'submetida_em' => now(),
            'codigo' => $codigo,
        ]);

        ItemRequisicao::factory()->create([
            'requisicao_id' => $requisicao->id,
            'item_catalogo_id' => null,
            'avulso' => true,
            'quantidade' => 1,
            'valor_unitario_estimado' => $valor,
        ]);

        return $requisicao;
    });
}

it('o relatório de emergenciais não traz o nome da unidade de outro tenant', function () {
    // O join com `unidades` casava só por id. Com uma requisição de A apontando (FK
    // cruzada) para a unidade de B, o NOME da unidade do vizinho ia para a tela de A.
    $req = req2a_emergencial($this->tenantA->id, $this->unidadeA->id, 100.0, 'REQ-2A-000001');
    DB::table('requisicoes')->where('id', $req->id)->update(['unidade_id' => $this->unidadeB->id]);

    Livewire::actingAs($this->adminA)
        ->test(ComprasEmergenciais::class)
        ->assertDontSee('Posto Bravo Centro');
});

it('as subconsultas do relatório somam só o tenant do relatório', function () {
    // As três derivadas (pc_val/cot_val/est_val) agregavam a instalação inteira antes
    // do join. Com uma linha de B pendurada (FK cruzada) na requisição de A, o valor
    // do vizinho entrava na conta — e, mesmo quando não entrava, o plano pagava o
    // volume do maior tenant da base.
    $reqA = req2a_emergencial($this->tenantA->id, $this->unidadeA->id, 100.0, 'REQ-2A-000002');

    TenantContext::runFor($this->tenantB->id, function () use ($reqA) {
        $itemDeB = ItemRequisicao::factory()->create([
            'requisicao_id' => $reqA->id,
            'item_catalogo_id' => null,
            'avulso' => true,
            'quantidade' => 1,
            'valor_unitario_estimado' => 99999.0,
        ]);

        // Garante o carimbo do tenant B mesmo com a FK apontando para a requisição de A.
        DB::table('requisicao_itens')->where('id', $itemDeB->id)->update(['tenant_id' => $this->tenantB->id]);
    });

    $componente = Livewire::actingAs($this->adminA)->test(ComprasEmergenciais::class);

    expect((float) $componente->viewData('totalValor'))->toBe(100.0);
});

// ─── Achado 11: gestor validado por membership ─────────────────────────────────

it('aceita como gestor da unidade o convidado (membership) e recusa o ex-membro com home aqui', function () {
    // A tela LISTA `User::membersOfActiveTenant()` (membership) mas VALIDAVA por
    // `users.tenant_id` (home): o convidado aparecia no select e era reprovado ao
    // salvar, e o ex-funcionário sem vínculo — mas com home aqui — passava.
    $convidado = usr2a_convidado($this->tenantB->id, $this->tenantA->id, 'Gestor Convidado');

    $exMembro = TenantContext::runFor($this->tenantA->id, fn () => User::factory()->create(
        ['tenant_id' => $this->tenantA->id, 'name' => 'Ex-membro Alfa']
    ));
    DB::table('tenant_user')
        ->where('user_id', $exMembro->getKey())
        ->where('tenant_id', $this->tenantA->id)
        ->update(['status' => 'inactive']);

    Livewire::actingAs($this->adminA)
        ->test(ListaUnidades::class)
        ->call('abrirCriar')
        ->set('nome', 'Obra com gestor convidado')
        ->set('tipo', 'obra')
        ->set('obraIniciadaEm', now()->toDateString())
        ->set('status', 'ativa')
        ->set('gestorId', $convidado->getKey())
        ->call('salvar')
        ->assertHasNoErrors('gestorId');

    Livewire::actingAs($this->adminA)
        ->test(ListaUnidades::class)
        ->call('abrirCriar')
        ->set('nome', 'Obra com ex-membro')
        ->set('tipo', 'obra')
        ->set('obraIniciadaEm', now()->toDateString())
        ->set('status', 'ativa')
        ->set('gestorId', $exMembro->getKey())
        ->call('salvar')
        ->assertHasErrors('gestorId');
});

// ─── Achado 11: aviso de reprovação vazando para compradoras de outro tenant ───

it('a reprovação avisa só quem é membro ATIVO do tenant da requisição', function () {
    // `User::whereHas('roles', slug=compras)` não filtrava nem o pivot `user_role`
    // (que é POR TENANT) nem a identidade, que é COMPARTILHADA pela suíte. O escopo de
    // tenant do model Role já continha o vazamento entre empresas (por isso a
    // `compradoraB` abaixo é regressão, não o achado); o que passava era o EX-MEMBRO:
    // quem teve o vínculo revogado mantém a linha em `user_role` e continuava
    // recebendo, por e-mail, código da requisição, justificativa e nome do aprovador.
    $compradoraA = TenantContext::runFor($this->tenantA->id, fn () => User::factory()->compradora()->create(
        ['tenant_id' => $this->tenantA->id, 'email' => 'compradora@alfa.test']
    ));
    $compradoraB = TenantContext::runFor($this->tenantB->id, fn () => User::factory()->compradora()->create(
        ['tenant_id' => $this->tenantB->id, 'email' => 'compradora@bravo.test']
    ));

    $desligada = TenantContext::runFor($this->tenantA->id, fn () => User::factory()->compradora()->create(
        ['tenant_id' => $this->tenantA->id, 'email' => 'desligada@alfa.test']
    ));
    DB::table('tenant_user')
        ->where('user_id', $desligada->getKey())
        ->where('tenant_id', $this->tenantA->id)
        ->update(['status' => 'inactive']);

    [$requisicao, $aprovador] = TenantContext::runFor($this->tenantA->id, function () {
        $solicitante = User::factory()->create(['tenant_id' => $this->tenantA->id]);
        $centro = CentroCusto::factory()->create(['unidade_id' => $this->unidadeA->id, 'ativo' => true]);

        $requisicao = Requisicao::factory()->create([
            'unidade_id' => $this->unidadeA->id,
            'centro_custo_id' => $centro->id,
            'solicitante_id' => $solicitante->id,
            'status' => StatusRequisicao::AguardandoAprovacao,
            'ciclo_aprovacao' => 1,
            'codigo' => 'REQ-2A-000003',
        ]);
        ItemRequisicao::factory()->create([
            'requisicao_id' => $requisicao->id, 'avulso' => true, 'item_catalogo_id' => null,
        ]);
        Aprovacao::create([
            'requisicao_id' => $requisicao->id, 'etapa_alcada_id' => null, 'ciclo' => 1, 'ordem' => 1,
            'nivel_exigido' => NivelAlcada::Gestor->value, 'obrigatoria_emergencial' => false,
            'status' => StatusAprovacao::Pendente->value,
        ]);

        $aprovador = User::factory()->create(['tenant_id' => $this->tenantA->id]);
        $aprovador->unidades()->attach($this->unidadeA->id, [
            'perfil' => Perfil::Aprovador->value,
            'nivel_alcada' => NivelAlcada::Gestor->value,
            'tenant_id' => $this->tenantA->id,
        ]);

        return [$requisicao, $aprovador];
    });

    TenantContext::runFor($this->tenantA->id, fn () => app(ReprovarRequisicaoAction::class)
        ->execute($requisicao, $aprovador, 'Fora do orçamento.'));

    Mail::assertSent(RequisicaoReprovada::class, fn ($mail) => $mail->hasTo($compradoraA->email));
    Mail::assertNotSent(RequisicaoReprovada::class, fn ($mail) => $mail->hasTo($desligada->email));
    Mail::assertNotSent(RequisicaoReprovada::class, fn ($mail) => $mail->hasTo($compradoraB->email));
});
