<?php

use App\Livewire\Admin\Usuarios\ListaUsuarios;
use App\Models\User;
use Helix\Foundation\Mail\TenantInvitationMail;
use Helix\Foundation\Models\Platform\Identity\Tenant;
use Helix\Foundation\Models\Platform\Identity\TenantInvitation;
use Helix\Foundation\Services\Platform\Identity\InvitationService;
use Helix\Foundation\Services\Platform\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Cadastro de usuário pela tela de administração.
 *
 * Fundação v0.5.0 (decisão 9): criar usuário virou CONVITE. O admin nunca define nem
 * vê a senha de ninguém (antes: `Str::random(10)` mostrada na tela como "senha
 * provisória"); a pessoa prova a posse do e-mail pelo link e cria a própria senha. O
 * vínculo nasce no aceite — ativo, com alcance CORPORATIVO declarado (achado ALTO
 * original: sem membership o login dava 403).
 */
beforeEach(function () {
    $this->tenant = Tenant::create(['slug' => 'alpha', 'name' => 'Alpha', 'status' => 'active']);

    // O beforeEach global (tests/Pest.php) deixa no contexto o tenant canônico
    // "comendador"; este arquivo trabalha em `alpha` — o que o SetActiveTenant faz no request real.
    TenantContext::set($this->tenant->id);

    $this->admin = User::factory()->admin()->create([
        'tenant_id' => $this->tenant->id, 'email' => 'admin@alpha.test',
    ]);
});

/** Token do convite enfileirado para o e-mail (só existe no link do e-mail). */
function cad_tokenDoConvite(string $email): string
{
    $url = null;
    Mail::assertQueued(TenantInvitationMail::class, function (TenantInvitationMail $mail) use ($email, &$url) {
        if (! $mail->hasTo($email)) {
            return false;
        }
        $url = $mail->url;

        return true;
    });

    return basename(parse_url((string) $url, PHP_URL_PATH));
}

it('novo usuário vira convite: o admin não cria identidade nem conhece senha', function () {
    Mail::fake();

    Livewire::actingAs($this->admin)
        ->test(ListaUsuarios::class)
        ->call('abrirCriar')
        ->set('email', 'novo@alpha.test')
        ->set('isAdmin', false)
        ->call('salvar')
        ->assertHasNoErrors()
        ->assertSet('mostrarModal', false)
        ->assertDispatched('notify');

    // Nada de identidade (logo, nada de senha) antes de a pessoa aceitar.
    expect(User::where('email', 'novo@alpha.test')->exists())->toBeFalse()
        ->and(property_exists(ListaUsuarios::class, 'senhaProvisoria'))->toBeFalse();

    $convite = TenantContext::runFor($this->tenant->id, fn () => TenantInvitation::query()->where('email', 'novo@alpha.test')->firstOrFail());
    expect($convite->access_scope)->toBe(User::SCOPE_CORPORATE)
        ->and($convite->branch_id)->toBeNull()
        ->and((bool) $convite->is_admin)->toBeFalse();

    // A pessoa aceita com a senha DELA: o vínculo nasce ativo e corporativo.
    TenantContext::forget();
    $novo = app(InvitationService::class)->acceptAsNewUser(cad_tokenDoConvite('novo@alpha.test'), 'Novo Usuário', 'S3nha-Da-Propria-Pessoa!');

    expect(DB::table('tenant_user')->where('user_id', $novo->id)->where('tenant_id', $this->tenant->id)->first())
        ->status->toBe('active')
        ->access_scope->toBe(User::SCOPE_CORPORATE)
        ->and(Hash::check('S3nha-Da-Propria-Pessoa!', $novo->password))->toBeTrue()
        ->and($novo->precisa_trocar_senha)->toBeFalse()
        ->and($novo->isAdminIn($this->tenant->id))->toBeFalse();

    // Nenhuma trilha de senha definida por admin.
    expect(DB::table('audit_logs')->where('action', 'user.created')->where('metadata', 'like', '%password_set_by_admin%')->exists())->toBeFalse();
});

it('convite de admin reflete is_admin no pivot após o aceite (não só na coluna)', function () {
    Mail::fake();

    Livewire::actingAs($this->admin)
        ->test(ListaUsuarios::class)
        ->call('abrirCriar')
        ->set('email', 'novoadmin@alpha.test')
        ->set('isAdmin', true)
        ->call('salvar')
        ->assertHasNoErrors();

    TenantContext::forget();
    $novo = app(InvitationService::class)->acceptAsNewUser(cad_tokenDoConvite('novoadmin@alpha.test'), 'Novo Admin', 'S3nha-Da-Propria-Pessoa!');

    expect($novo->isAdminIn($this->tenant->id))->toBeTrue();
});

it('editar is_admin sincroniza o pivot (revogar desescala de verdade)', function () {
    $alvo = User::factory()->admin()->create([
        'tenant_id' => $this->tenant->id, 'email' => 'alvo@alpha.test',
    ]);
    expect($alvo->isAdminForActiveTenant())->toBeTrue();

    Livewire::actingAs($this->admin)
        ->test(ListaUsuarios::class)
        ->call('abrirEditar', $alvo->id)
        ->set('isAdmin', false)
        ->call('salvar');

    expect($alvo->fresh()->isAdminForActiveTenant())->toBeFalse();
});
