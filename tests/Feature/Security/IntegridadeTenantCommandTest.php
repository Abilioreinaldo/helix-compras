<?php

use App\Models\Cotacao;
use App\Models\Fornecedor;
use App\Models\Requisicao;
use App\Models\User;
use Helix\Foundation\Models\Platform\Identity\Tenant;
use Helix\Foundation\Services\Platform\Support\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/*
| Decisão 15 — relatório de saneamento `helix:integridade-tenant` (SÓ LEITURA).
| Dados sintéticos cruzados/órfãos em :memory:; nenhum teste roda contra base local.
*/

beforeEach(function () {
    $this->tenantA = TenantContext::id();
    $this->tenantB = Tenant::create(['slug' => 'integ-b', 'name' => 'Integ B', 'status' => 'active'])->id;

    $this->cotacaoA = Cotacao::factory()->create(['valor' => 10]);
    $this->requisicaoB = TenantContext::runFor($this->tenantB, fn () => Requisicao::factory()->create());

    TenantContext::forget();
});

/** @return array{0: int, 1: array<string, mixed>} [exit code, relatório JSON] */
function integRodar(): array
{
    $codigo = Artisan::call('helix:integridade-tenant', ['--json' => true]);

    return [$codigo, json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)];
}

/** @param array<string, mixed> $relatorio */
function integFk(array $relatorio, string $filha, string $coluna): array
{
    return collect($relatorio['fks'])->firstOrFail(fn ($fk) => $fk['filha'] === $filha && $fk['coluna'] === $coluna);
}

it('base íntegra: exit 0, FKs descobertas do schema e saída ordenada mãe → filha', function () {
    [$codigo, $r] = integRodar();

    $ordem = collect($r['fks'])->map(fn ($fk) => $fk['filha'].'.'.$fk['coluna'])->values();

    expect($codigo)->toBe(0)
        ->and($r['resumo']['integro'])->toBeTrue()
        ->and($ordem)->toContain('requisicoes.unidade_id', 'cotacoes.requisicao_id', 'itens_cotacao.cotacao_id', 'unidade_user.unidade_id', 'cotacao_links.cotacao_id')
        ->and($ordem->search('requisicoes.unidade_id'))->toBeLessThan($ordem->search('cotacoes.requisicao_id'))
        ->and($ordem->search('cotacoes.requisicao_id'))->toBeLessThan($ordem->search('itens_cotacao.cotacao_id'))
        // FK para users, para tenants e para catálogo global (bancos) não são FK de domínio.
        ->and($ordem)->not->toContain('cotacoes.criada_por')
        ->and($ordem)->not->toContain('cotacoes.tenant_id')
        ->and($ordem->filter(fn ($fk) => str_contains($fk, 'banco'))->all())->toBe([])
        ->and(collect($r['fks_usuarios'])->map(fn ($fk) => $fk['filha'].'.'.$fk['coluna'])->all())->toContain('cotacoes.criada_por', 'requisicoes.solicitante_id', 'unidade_user.user_id');
});

it('conta CRUZADO (mãe em outro tenant) e sai com código de erro', function () {
    DB::table('cotacoes')->where('id', $this->cotacaoA->id)->update(['requisicao_id' => $this->requisicaoB->id]);

    [$codigo, $r] = integRodar();

    expect($codigo)->toBe(1)
        ->and(integFk($r, 'cotacoes', 'requisicao_id')['cruzados'])->toBe(1)
        ->and(integFk($r, 'cotacoes', 'requisicao_id')['orfaos'])->toBe(0)
        ->and($r['resumo']['integro'])->toBeFalse();
});

it('conta ÓRFÃO (mãe inexistente) e sai com código de erro', function () {
    // FK adiada até o COMMIT — que nunca vem (RefreshDatabase faz rollback): simula o
    // restore/import com foreign_key_checks=0 que o relatório existe para achar.
    DB::statement('PRAGMA defer_foreign_keys = ON');
    DB::table('cotacoes')->where('id', $this->cotacaoA->id)->update(['fornecedor_id' => 987654]);

    [$codigo, $r] = integRodar();

    expect($codigo)->toBe(1)
        ->and(integFk($r, 'cotacoes', 'fornecedor_id')['orfaos'])->toBe(1)
        ->and(integFk($r, 'cotacoes', 'fornecedor_id')['cruzados'])->toBe(0);
})->skip(fn () => DB::getDriverName() !== 'sqlite', 'órfão sintético via PRAGMA defer_foreign_keys (SQLite)');

it('conta tenant_id nulo por tabela', function () {
    TenantContext::runFor($this->tenantA, fn () => Fornecedor::factory()->count(2)->create());
    DB::table('fornecedores')->update(['tenant_id' => null]);
    $total = DB::table('fornecedores')->count();

    [$codigo, $r] = integRodar();

    expect(collect($r['tenant_id_nulos'])->firstWhere('tabela', 'fornecedores')['nulos'])->toBe($total)
        ->and($r['resumo']['tenant_id_nulos'])->toBeGreaterThanOrEqual($total)
        ->and($codigo)->toBe(0); // nulo é reportado, mas só órfão/cruzado reprova
});

it('autoria sem vínculo ativo no tenant é só INFORMATIVA (não reprova)', function () {
    $userB = User::factory()->create(['tenant_id' => $this->tenantB]);
    DB::table('cotacoes')->where('id', $this->cotacaoA->id)->update(['criada_por' => $userB->id]);

    [$codigo, $r] = integRodar();

    $criadaPor = collect($r['fks_usuarios'])->firstOrFail(fn ($fk) => $fk['filha'] === 'cotacoes' && $fk['coluna'] === 'criada_por');

    expect($codigo)->toBe(0)
        ->and($criadaPor['sem_vinculo_ativo'])->toBe(1);
});

it('não escreve nada: listener recusa INSERT/UPDATE/DELETE/DDL durante o relatório', function () {
    DB::table('cotacoes')->where('id', $this->cotacaoA->id)->update(['requisicao_id' => $this->requisicaoB->id]);

    $recusados = [];
    DB::beforeExecuting(function (string $sql) use (&$recusados) {
        if (! preg_match('/^\s*(select|with)\b/i', $sql)) {
            $recusados[] = $sql;

            throw new RuntimeException('Relatório de integridade tentou escrever: '.$sql);
        }
    });

    // O listener funciona (prova de que um write seria barrado)...
    expect(fn () => DB::table('fornecedores')->where('id', 0)->delete())->toThrow(RuntimeException::class);
    $recusados = [];

    // ...e o relatório, em texto e em JSON, roda inteiro sem disparar nenhum.
    expect(Artisan::call('helix:integridade-tenant'))->toBe(1)
        ->and(Artisan::call('helix:integridade-tenant', ['--json' => true]))->toBe(1)
        ->and($recusados)->toBe([]);
});
