<?php

namespace App\Livewire\Admin\Usuarios;

use App\Enums\NivelAlcada;
use App\Enums\Perfil;
use App\Models\Scopes\UnidadeScope;
use App\Models\Unidade;
use App\Models\User;
use Helix\Foundation\Exceptions\IdentityConflictException;
use Helix\Foundation\Models\Platform\Identity\Role;
use Helix\Foundation\Services\Platform\Identity\InvitationService;
use Helix\Foundation\Services\Platform\Identity\MembershipService;
use Helix\Foundation\Services\Platform\Identity\UserService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Usuários do tenant, pelo admin da empresa. Papéis são os do catálogo da
 * fundação (o que cada um pode fazer se ajusta em Papéis & Permissões); o
 * vínculo unidade × perfil operacional × alçada é domínio do Compras.
 * Edição passa pelo UserService (membership, papéis, evento + auditoria).
 *
 * Fundação v0.5.0 (decisão 9): NOVO usuário é CONVITE (InvitationService) — o admin
 * nunca define nem vê a senha de ninguém; o dono do e-mail cria a própria senha (ou
 * entra na conta que já tem) pelo link. Alcance do vínculo: sempre CORPORATIVO,
 * declarado — o recorte por unidade do Compras é o vínculo unidade × perfil
 * (unidade_user), não a filial da fundação.
 */
class ListaUsuarios extends Component
{
    use WithPagination;

    /** Resposta ÚNICA da exclusão — apague-se a identidade ou só o vínculo (sem oráculo). */
    private const MENSAGEM_REMOVIDO = 'Usuário removido desta empresa.';

    public string $busca = '';

    public bool $mostrarModal = false;

    // Locked: identidades de usuário vêm do servidor (abrirEditar/abrirVinculos); o cliente não reaponta.
    #[Locked]
    public ?int $editandoId = null;

    // Campos do formulário principal
    public string $name = '';

    public string $email = '';

    public bool $isAdmin = false;

    /** @var array<int, string> ids dos papéis (roles) atribuídos */
    public array $papeis = [];

    public string $status = 'active';

    // Modal de vínculos
    public bool $mostrarModalVinculos = false;

    #[Locked]
    public ?int $usuarioVinculosId = null;

    public ?int $vincularUnidadeId = null;

    public string $vincularPerfil = '';

    public string $vincularNivelAlcada = '';

    public function abrirCriar(): void
    {
        abort_unless(auth()->user()->can('users.manage'), 403);
        $this->resetValidation();
        $this->editandoId = null;
        $this->name = '';
        $this->email = '';
        $this->isAdmin = false;
        $this->papeis = [];
        $this->status = 'active';
        $this->mostrarModal = true;
    }

    public function abrirEditar(int $id): void
    {
        abort_unless(auth()->user()->can('users.manage'), 403);
        $this->resetValidation();
        $usuario = $this->usuariosDoTenant()->findOrFail($id);
        abort_unless(auth()->user()->can('operar', $usuario), 403);
        // Identidade é COMPARTILHADA na suíte: editar nome/e-mail/status/papéis de um
        // convidado (home noutro tenant) escreveria no tenant DELE. Ver notaGuest().
        abort_if($this->ehConvidado($usuario), 403, 'Usuário convidado de outro tenant: gerencie apenas o vínculo.');
        $tenantId = auth()->user()->getActiveTenantId();

        $this->editandoId = $id;
        $this->name = $usuario->name;
        $this->email = $usuario->email;
        // Admin é propriedade do TENANT (pivot tenant_user.is_admin), não a coluna global.
        $this->isAdmin = $this->ehAdminNoTenant($usuario, $tenantId);
        $this->papeis = $usuario->roles()
            ->wherePivot('tenant_id', $tenantId)
            ->pluck('roles.id')->map(fn ($id) => (string) $id)->all();
        $this->status = $usuario->status;
        $this->mostrarModal = true;
    }

    public function salvar(UserService $users, InvitationService $convites): void
    {
        abort_unless(auth()->user()->can('users.manage'), 403);

        $tenantId = auth()->user()->getActiveTenantId();

        // SEM `Rule::unique('users','email')` (3ª auditoria adversarial): `users.email`
        // é unique GLOBAL da suíte, e o validador rodando ANTES do UserService respondia
        // "Este e-mail já está em uso." — oráculo de quem tem conta em QUALQUER cliente.
        $regrasPapeis = [
            'isAdmin' => 'boolean',
            'papeis' => 'array',
            'papeis.*' => [Rule::exists('roles', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at')],
        ];

        if (! $this->editandoId) {
            $this->convidar($convites, (string) $tenantId, $regrasPapeis);

            return;
        }

        $this->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255'],
            'status' => 'required|in:active,inactive',
        ] + $regrasPapeis, [
            'name.required' => 'O nome é obrigatório.',
            'email.required' => 'O e-mail é obrigatório.',
        ]);

        $usuario = $this->usuariosDoTenant()->findOrFail($this->editandoId);
        abort_unless(auth()->user()->can('operar', $usuario), 403);
        abort_if($this->ehConvidado($usuario), 403, 'Usuário convidado de outro tenant: gerencie apenas o vínculo.');
        $statusAntigo = $usuario->status;

        // IDENTIDADE COMPARTILHADA (fundação v0.5.0, decisão 8): nome, e-mail e telefone
        // são da PESSOA. Quem participa de mais de uma empresa (vínculo em qualquer status)
        // só os altera ela mesma; a guarda é do UserService, que recusa a chamada inteira
        // com IdentityConflictException. Só os campos ALTERADOS vão na chamada — o
        // vínculo (admin/papéis) de uma identidade compartilhada segue editável aqui.
        // A tela não diz POR QUE recusou (antes: "participa de outra empresa"): a
        // mensagem é a genérica da fundação, sem revelar vínculos noutros clientes.
        //
        // v0.7.0 (4ª auditoria, FUNDACAO-1): o E-MAIL é a chave da identidade — quem o
        // troca passa a receber os links de redefinição e os convites da pessoa. Terceiro
        // não troca NEM COM PERMISSÃO, e nem a própria pessoa troca "no seco" (o caminho é
        // o EmailChangeService, com confirmação no endereço NOVO). O campo saiu do
        // formulário de edição; o payload continua levando o e-mail divergente de
        // propósito, para que a recusa venha da GUARDA DA FUNDAÇÃO — e não de uma cópia
        // caseira dela nesta tela, que envelheceria sozinha.
        $dados = ['is_admin' => $this->isAdmin];
        $alterados = [];

        if (trim($this->name) !== (string) $usuario->name) {
            $dados['name'] = trim($this->name);
            $alterados[] = 'name';
        }

        if (strcasecmp(trim($this->email), (string) $usuario->email) !== 0) {
            $dados['email'] = trim($this->email);
            $alterados[] = 'email';
        }

        try {
            $users->updateUser($usuario, $dados, $this->papeis, auth()->user());
        } catch (IdentityConflictException $e) {
            // Com e-mail no payload a recusa é SEMPRE sobre ele (a fundação lança antes de
            // olhar os demais campos): o erro tem de pousar no campo do e-mail, senão a
            // tela aponta o dedo para o "nome" numa edição que mexeu nos dois.
            $this->addError(array_key_exists('email', $dados) ? 'email' : ($alterados[0] ?? 'email'), $e->getMessage());

            return;
        } catch (UniqueConstraintViolationException) {
            $this->addError('email', IdentityConflictException::forEmailChange()->getMessage());

            return;
        }

        // `status` mora na IDENTIDADE (users.status), não na membership: inativar
        // aqui derrubaria o acesso do usuário em TODOS os tenants dele. Só é
        // permitido quando este tenant é o único vínculo — caso contrário, o
        // caminho correto é remover o vínculo (excluir). A fundação v0.4.0 já oferece
        // `UserService::suspendMembership` (tenant_user.status); expô-lo nesta tela é
        // decisão de produto pendente.
        if ($statusAntigo !== $this->status) {
            // Qualquer vínculo externo, EM QUALQUER STATUS (fundação v0.4.0): um vínculo
            // suspenso noutra empresa ainda é uma porta para ela, e o UserService recusa
            // (TenantMismatchException) — antes a tela só olhava vínculo ATIVO e o
            // inativar/reativar da identidade compartilhada passava daqui.
            if ($this->temVinculoForaDaqui($usuario)) {
                // COMPRAS-3 (4ª auditoria): a mensagem NÃO diz por que recusou (antes: "também
                // participa de outra empresa" — oráculo de vínculos de um e-mail na suíte).
                // Mesmo tom do IdentityConflictException: o que fazer, não o motivo.
                $this->addError('status', 'Não foi possível alterar o status deste usuário por aqui. Para tirar o acesso dele a esta empresa, use "Excluir".');

                return;
            }

            $users->changeStatus($usuario, $this->status, auth()->user());
        }

        $this->mostrarModal = false;
        $this->dispatch('notify', mensagem: 'Usuário salvo com sucesso.');
    }

    /**
     * NOVO usuário = CONVITE (fundação v0.5.0, decisão 9). O admin informa só e-mail,
     * papéis e se é admin; a pessoa define a própria senha no aceite (ou entra na conta
     * que já tem). Sem oráculo: a resposta é a MESMA exista ou não conta com o e-mail.
     *
     * @param  array<string, mixed>  $regrasPapeis
     */
    private function convidar(InvitationService $convites, string $tenantId, array $regrasPapeis): void
    {
        $this->validate([
            'email' => ['required', 'email', 'max:255'],
        ] + $regrasPapeis, [
            'email.required' => 'O e-mail é obrigatório.',
        ]);

        try {
            $convites->invite(
                $tenantId,
                $this->email,
                array_map('strval', $this->papeis),
                User::SCOPE_CORPORATE,
                null,
                auth()->user(),
                isAdmin: $this->isAdmin,
            );
        } catch (ThrottleRequestsException $e) {
            $this->addError('email', $e->getMessage());

            return;
        }

        $this->mostrarModal = false;
        $this->dispatch('notify', mensagem: 'Convite enviado. A pessoa recebe por e-mail o link para acessar esta empresa.');
    }

    /**
     * Remove o usuário DESTA empresa. Se ele participa de outra, só o VÍNCULO com
     * este tenant cai (identidade e acesso nos demais ficam intactos); se este é o
     * único vínculo, a identidade é removida como antes.
     */
    public function excluir(int $id, UserService $users): void
    {
        abort_unless(auth()->user()->can('users.manage'), 403);

        // Revogar alcança também o vínculo SUSPENSO (fundação v0.7.0, `membersOf` com
        // status): quem está suspenso aqui continua tendo uma porta para esta empresa, e
        // era exatamente quem o admin não conseguia tirar.
        $usuario = $this->usuariosGerenciaveis()->findOrFail($id);
        abort_unless(auth()->user()->can('revogar', $usuario), 403);
        $tenantId = auth()->user()->getActiveTenantId();

        // CONVIDADO (home noutro tenant) nunca tem a IDENTIDADE apagada por este admin —
        // só o vínculo. Olhar só o vínculo ATIVO não bastava: bastava que o vínculo
        // do convidado com a empresa DELE estivesse inativo (ou já removido) para o
        // admin daqui cair no `deleteUser` e soft-deletar uma identidade que não é sua,
        // com a trilha de auditoria nascendo no tenant home — a outra empresa perdia o
        // usuário e via na sua própria auditoria um ator de fora. As demais escritas já
        // tinham esta guarda (abrirEditar/salvar); a exclusão era a que faltava.
        //
        // Fundação v0.4.0: o vínculo externo conta EM QUALQUER STATUS. Quem tem home aqui e
        // um vínculo SUSPENSO/INATIVO noutra empresa caía no deleteUser e perdia a
        // identidade — a conta com que a outra empresa o reativaria.
        //
        // Fundação v0.7.0: o vínculo SUSPENSO aqui também cai neste ramo. `deleteUser`
        // exige membro ATIVO (e apagaria a IDENTIDADE); sobre quem já não tem acesso a
        // esta empresa o que o admin daqui pode — e precisa — fazer é fechar a porta,
        // revogando o vínculo. A identidade não é dele para apagar.
        if ($this->ehConvidado($usuario) || $this->temVinculoForaDaqui($usuario) || ! $this->ehMembroAtivo($usuario, (string) $tenantId)) {
            // Vínculos por unidade deste tenant caem junto (o pivot é escopado).
            DB::table('unidade_user')
                ->where('user_id', $usuario->getKey())
                ->where('tenant_id', $tenantId)
                ->delete();

            $users->removeMembership($usuario, (string) $tenantId, auth()->user());
            // COMPRAS-3 (4ª auditoria): MESMA mensagem dos dois ramos. "Segue ativo nas
            // demais" contava ao admin daqui que a pessoa tem vínculo noutra empresa (e
            // ainda mentia quando o vínculo de lá estava suspenso).
            $this->dispatch('notify', mensagem: self::MENSAGEM_REMOVIDO);

            return;
        }

        $users->deleteUser($usuario, auth()->user());
        $this->dispatch('notify', mensagem: self::MENSAGEM_REMOVIDO);
    }

    public function abrirVinculos(int $id): void
    {
        abort_unless(auth()->user()->can('users.manage'), 403);
        // Anti-IDOR: o usuário alvo precisa ser do tenant ativo.
        $usuario = $this->usuariosDoTenant()->findOrFail($id);
        abort_unless(auth()->user()->can('operar', $usuario), 403);
        $this->usuarioVinculosId = $usuario->id;
        $this->vincularUnidadeId = null;
        $this->vincularPerfil = '';
        $this->vincularNivelAlcada = '';
        $this->mostrarModalVinculos = true;
    }

    public function adicionarVinculo(): void
    {
        abort_unless(auth()->user()->can('users.manage'), 403);

        $this->validate([
            'vincularUnidadeId' => ['required', Rule::exists('unidades', 'id')->whereNull('deleted_at')->where('tenant_id', auth()->user()->getActiveTenantId())],
            'vincularPerfil' => ['required', 'in:'.implode(',', array_map(fn (Perfil $p) => $p->value, Perfil::porUnidade()))],
            'vincularNivelAlcada' => 'nullable|in:'.implode(',', array_column(NivelAlcada::cases(), 'value')),
        ], [
            'vincularUnidadeId.required' => 'Selecione uma unidade.',
            'vincularPerfil.required' => 'Selecione um perfil.',
        ]);

        $usuario = $this->usuariosDoTenant()->findOrFail($this->usuarioVinculosId);
        abort_unless(auth()->user()->can('operar', $usuario), 403);

        // O vínculo é por (unidade, PERFIL): a pessoa pode ser solicitante E aprovadora
        // da mesma unidade (unique tenant+user+unidade+perfil). `syncWithoutDetaching`
        // indexado pela unidade tratava o segundo perfil como UPDATE do primeiro — o
        // perfil anterior sumia sem aviso (aceite 25/09). Mesmo perfil de novo = só o
        // nível de alçada é atualizado.
        $existente = $usuario->unidades()
            ->wherePivot('perfil', $this->vincularPerfil)
            ->where('unidades.id', $this->vincularUnidadeId)
            ->exists();

        if ($existente) {
            $usuario->unidades()
                ->wherePivot('perfil', $this->vincularPerfil)
                ->updateExistingPivot($this->vincularUnidadeId, ['nivel_alcada' => $this->vincularNivelAlcada ?: null]);
        } else {
            $usuario->unidades()->attach($this->vincularUnidadeId, [
                'perfil' => $this->vincularPerfil,
                'nivel_alcada' => $this->vincularNivelAlcada ?: null,
            ]);
        }

        $this->vincularUnidadeId = null;
        $this->vincularPerfil = '';
        $this->vincularNivelAlcada = '';
        $this->dispatch('notify', mensagem: $existente ? 'Nível de alçada do vínculo atualizado.' : 'Vínculo adicionado.');
    }

    public function removerVinculo(int $unidadeId, string $perfil): void
    {
        abort_unless(auth()->user()->can('users.manage'), 403);
        $usuario = $this->usuariosDoTenant()->findOrFail($this->usuarioVinculosId);
        abort_unless(auth()->user()->can('operar', $usuario), 403);
        // Remove SÓ o vínculo (unidade, perfil) clicado — os outros perfis da mesma unidade ficam.
        $usuario->unidades()->wherePivot('perfil', $perfil)->detach($unidadeId);
        $this->dispatch('notify', mensagem: 'Vínculo removido.');
    }

    /**
     * Base de usuários do tenant ativo pela MEMBERSHIP (pivot `tenant_user`), não
     * por `users.tenant_id` — que é só o tenant HOME da identidade compartilhada.
     *
     * Filtrar pelo home errava nas duas direções: quem tem home aqui mas já teve o
     * vínculo revogado continuava administrável (e uma inativação derrubava o acesso
     * dele nas outras empresas), e quem trabalha aqui com home noutra empresa ficava
     * INVISÍVEL para o admin desta. Membership ativa é a única definição de "é gente
     * desta empresa" — a mesma que a autorização usa (User::belongsToTenant).
     *
     * Fundação v0.5.0: `User::membersOf` é a consulta canônica (vínculo ATIVO, com o
     * vínculo pré-carregado para isAdminIn sem N+1). Vínculo `pending_scope` NÃO é
     * ativo: fica fora daqui e aparece em vinculosPendentes().
     */
    private function usuariosDoTenant()
    {
        return User::membersOf((string) auth()->user()->getActiveTenantId());
    }

    /**
     * Base da LISTAGEM e da REVOGAÇÃO: vínculos ativos E suspensos (fundação v0.7.0,
     * `membersOf($t, $statuses)`).
     *
     * `membersOf()` sozinho só traz vínculo ATIVO — e quem está SUSPENSO nesta empresa
     * ficava INVISÍVEL para o admin dela: ele não via que a pessoa ainda tem uma porta
     * aberta aqui e, pior, não conseguia tirá-la (o `excluir` resolvia pela consulta de
     * ativos e respondia 404). Suspender é operação do console hoje; revogar o vínculo
     * tem de continuar sendo desta tela.
     *
     * As demais ações (editar, papéis, vínculos por unidade, alcance) seguem só sobre
     * vínculo ATIVO, por desenho: reativar alguém é decisão de produto pendente, e
     * fail-closed é o default certo enquanto ela não existe.
     */
    private function usuariosGerenciaveis()
    {
        return User::membersOf((string) auth()->user()->getActiveTenantId(), ['active', 'suspended']);
    }

    /**
     * Ids (desta página) cujo vínculo com esta empresa está SUSPENSO — o pivot já vem
     * pré-carregado pelo membersOf, sem consulta por linha.
     *
     * @param  iterable<int, User>  $usuarios
     * @return array<int, int>
     */
    private function suspensosNoTenant(iterable $usuarios, string $tenantId): array
    {
        $ids = [];

        foreach ($usuarios as $usuario) {
            $vinculo = $usuario->memberships->firstWhere('id', $tenantId);

            if ($vinculo !== null && ($vinculo->pivot->status ?? null) === 'suspended') {
                $ids[] = (int) $usuario->id;
            }
        }

        return $ids;
    }

    /**
     * Vínculos DESTA empresa aguardando alcance (`pending_scope`, fundação v0.5.0):
     * pessoa criada por atalho sem alcance declarado, ou cuja filial legada não valia
     * para este tenant (backfill da migration). Não dá acesso até o admin definir.
     */
    private function vinculosPendentes()
    {
        $tenantId = auth()->user()->getActiveTenantId();

        return User::query()->whereExists(fn ($q) => $q
            ->selectRaw('1')
            ->from('tenant_user')
            ->whereColumn('tenant_user.user_id', 'users.id')
            ->where('tenant_user.tenant_id', $tenantId)
            ->where('tenant_user.status', User::MEMBERSHIP_PENDING_SCOPE));
    }

    /**
     * Define o alcance de um vínculo `pending_scope` desta empresa (e o ativa). No
     * Compras o alcance é sempre CORPORATIVO — escolha explícita, auditada pelo
     * MembershipService (user.membership_scope_changed; step-up se houver guarda): o
     * recorte por unidade continua sendo o vínculo unidade × perfil.
     */
    public function definirAlcance(int $id, MembershipService $vinculos): void
    {
        abort_unless(auth()->user()->can('users.manage'), 403);

        // Anti-IDOR: só vínculo PENDENTE deste tenant; id de outra empresa (ou de quem
        // já está ativo/suspenso aqui) não casa e responde 404. A policy confere o
        // MESMO recorte sobre o registro (UsuarioPolicy::definirAlcance), antes da escrita.
        $usuario = $this->vinculosPendentes()->findOrFail($id);
        abort_unless(auth()->user()->can('definirAlcance', $usuario), 403);

        $vinculos->setScope($usuario, (string) auth()->user()->getActiveTenantId(), User::SCOPE_CORPORATE, null, auth()->user());

        $this->dispatch('notify', mensagem: 'Alcance definido: o usuário já pode acessar esta empresa.');
    }

    /** O usuário é CONVIDADO aqui? (participa deste tenant, mas sua identidade mora noutro). */
    private function ehConvidado(User $usuario): bool
    {
        return (string) ($usuario->getAttributes()['tenant_id'] ?? '') !== (string) auth()->user()->getActiveTenantId();
    }

    /** O usuário tem QUALQUER vínculo (ativo, suspenso ou inativo) com outro tenant? */
    private function temVinculoForaDaqui(User $usuario): bool
    {
        return DB::table('tenant_user')
            ->where('user_id', $usuario->getKey())
            ->where('tenant_id', '!=', auth()->user()->getActiveTenantId())
            ->exists();
    }

    /** O vínculo com ESTA empresa está ativo? (suspenso ≠ membro) */
    private function ehMembroAtivo(User $usuario, string $tenantId): bool
    {
        return DB::table('tenant_user')
            ->where('user_id', $usuario->getKey())
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->exists();
    }

    /** Admin DESTE tenant (pivot), não a coluna global `users.is_admin`. */
    private function ehAdminNoTenant(User $usuario, ?string $tenantId): bool
    {
        return DB::table('tenant_user')
            ->where('user_id', $usuario->getKey())
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where('is_admin', true)
            ->exists();
    }

    public function render(): View
    {
        $tenantId = auth()->user()->getActiveTenantId();

        $usuarios = $this->usuariosGerenciaveis()
            // Papéis são POR TENANT (pivot user_role.tenant_id): sem o filtro, a tela
            // mostraria a um admin daqui os papéis que o convidado tem na empresa dele.
            ->with(['roles' => fn ($q) => $q->where('user_role.tenant_id', $tenantId)])
            ->when($this->busca, fn ($q) => $q->where(function ($inner) {
                $inner->where('name', 'like', "%{$this->busca}%")
                    ->orWhere('email', 'like', "%{$this->busca}%");
            }))
            ->orderBy('name')
            ->paginate(15);

        // Admin e "convidado" são propriedades do VÍNCULO com este tenant: o pivot já vem
        // pré-carregado pelo membersOf (sem consulta por linha).
        $adminsDoTenant = $usuarios->getCollection()
            ->filter(fn (User $u) => $u->isAdminIn((string) $tenantId))
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
        $convidados = $usuarios->filter(fn (User $u) => $this->ehConvidado($u))->pluck('id')->all();
        // Vínculo SUSPENSO: a linha aparece (senão o admin não sabe que a porta existe),
        // mas a única ação oferecida é revogar — reativar é decisão de produto pendente.
        $suspensos = $this->suspensosNoTenant($usuarios->getCollection(), (string) $tenantId);

        $usuarioVinculos = $this->usuarioVinculosId
            ? $this->usuariosDoTenant()->with(['unidades' => fn ($q) => $q->withoutGlobalScope(UnidadeScope::class)->where('unidades.tenant_id', $tenantId)])->find($this->usuarioVinculosId)
            : null;

        $pendentes = $this->vinculosPendentes()->orderBy('name')->get(['id', 'name', 'email']);

        $todasUnidades = Unidade::withoutGlobalScope(UnidadeScope::class)->where('tenant_id', $tenantId)->orderBy('nome')->get();
        $papeisDisponiveis = Role::where('tenant_id', $tenantId)->orderByDesc('is_system')->orderBy('name')->get();
        $perfis = Perfil::porUnidade();
        $niveisAlcada = NivelAlcada::cases();

        return view('livewire.admin.usuarios.lista-usuarios', compact(
            'usuarios',
            'usuarioVinculos',
            'todasUnidades',
            'papeisDisponiveis',
            'perfis',
            'niveisAlcada',
            'adminsDoTenant',
            'convidados',
            'suspensos',
            'pendentes',
        ))->layout('components.layouts.app');
    }
}
