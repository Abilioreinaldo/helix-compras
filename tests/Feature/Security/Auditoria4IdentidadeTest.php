<?php

/**
 * 4ª auditoria adversarial — COMPRAS-3 (BAIXO).
 *
 * As sondas P4-U1/P4-U2 provaram que a tela de usuários conta ao admin DESTA empresa que
 * a pessoa tem vínculo em OUTRA: a exclusão respondia "removido desta empresa (segue ativo
 * nas demais)" — inclusive com o vínculo externo SUSPENSO — e o erro de status dizia
 * "também participa de outra empresa". O `salvar()` já tinha neutralizado o oráculo de
 * e-mail; estes dois caminhos ficaram para trás.
 */

use App\Livewire\Admin\Usuarios\ListaUsuarios;
use App\Models\User;
use Helix\Foundation\Models\Platform\Identity\Tenant;
use Helix\Foundation\Services\Platform\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();
    $this->tenantA = TenantContext::id();
    $this->tenantB = Tenant::create(['slug' => 'bravo-a4i', 'name' => 'Bravo', 'status' => 'active'])->id;
    $this->admin = User::factory()->admin()->create(['email' => 'admin@a4i.test']);
});

function a4i_membro(User $usuario, string $tenantId, string $status): void
{
    $usuario->memberships()->syncWithoutDetaching([$tenantId => ['status' => $status, 'is_admin' => false, 'access_scope' => 'corporate', 'branch_id' => null]]);
}

/** Texto de TUDO que a ação devolveu ao navegador (notify + erros). */
function a4i_saida(Testable $tela): string
{
    return json_encode([$tela->effects['dispatches'] ?? [], $tela->errors()->toArray()], JSON_UNESCAPED_UNICODE);
}

it('COMPRAS-3: excluir responde a MESMA mensagem para quem só existe aqui e para quem tem vínculo noutra empresa', function (string $statusLa) {
    $soDaqui = User::factory()->create(['email' => 'sodaqui@a4i.test']);
    $compartilhado = User::factory()->create(['email' => 'compart@a4i.test']);
    a4i_membro($compartilhado, $this->tenantB, $statusLa);

    $local = Livewire::actingAs($this->admin)->test(ListaUsuarios::class)->call('excluir', $soDaqui->id);
    $externo = Livewire::actingAs($this->admin)->test(ListaUsuarios::class)->call('excluir', $compartilhado->id);

    expect(a4i_saida($externo))->toBe(a4i_saida($local))
        ->and(a4i_saida($externo))->not->toContain('demais')->not->toContain('outra');

    // O comportamento protegido continua: a identidade compartilhada NÃO é apagada, só o vínculo daqui.
    expect(DB::table('users')->where('id', $compartilhado->id)->whereNull('deleted_at')->exists())->toBeTrue()
        ->and(DB::table('tenant_user')->where('user_id', $compartilhado->id)->where('tenant_id', $this->tenantA)->exists())->toBeFalse()
        ->and(DB::table('tenant_user')->where('user_id', $compartilhado->id)->where('tenant_id', $this->tenantB)->value('status'))->toBe($statusLa);
})->with(['vínculo externo ativo' => 'active', 'vínculo externo suspenso' => 'suspended']);

it('COMPRAS-3: o erro de status não cita outra empresa nem o motivo da recusa', function () {
    $compartilhado = User::factory()->create(['email' => 'compart2@a4i.test']);
    a4i_membro($compartilhado, $this->tenantB, 'inactive');

    $tela = Livewire::actingAs($this->admin)->test(ListaUsuarios::class)
        ->call('abrirEditar', $compartilhado->id)->set('status', 'inactive')->call('salvar');

    $erro = (string) $tela->errors()->first('status');
    expect($erro)->not->toBe('')
        ->and(mb_strtolower($erro))->not->toContain('outra empresa')->not->toContain('participa')->not->toContain('demais')->not->toContain(' lá');

    // A identidade global segue ativa (inativar aqui derrubaria o acesso dele na outra empresa).
    expect(DB::table('users')->where('id', $compartilhado->id)->value('status'))->toBe('active');
});
