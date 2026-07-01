<?php
/**
 * Seed aditivo (não destrutivo) para enriquecer os screenshots do manual:
 *  - cria preços homologados para alguns itens de catálogo;
 *  - cria uma requisição em "aguardando triagem" 100% homologada (mostra a Via Expressa).
 * Uso: php docs/manual/seed_manual.php
 */
require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\StatusRequisicao;
use App\Models\CatalogoItem;
use App\Models\CentroCusto;
use App\Models\FaixaAlcada;
use App\Models\Fornecedor;
use App\Models\ItemRequisicao;
use App\Models\PrecoHomologado;
use App\Models\Requisicao;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Support\Facades\DB;

DB::transaction(function () {
    $fornecedor = Fornecedor::where('homologado', true)->where('ativo', true)->first()
        ?? Fornecedor::where('ativo', true)->first();
    if ($fornecedor && ! $fornecedor->homologado) {
        $fornecedor->update(['homologado' => true, 'homologado_em' => now()]);
    }

    $itens = CatalogoItem::where('ativo', true)->take(3)->get();

    foreach ($itens as $i => $item) {
        PrecoHomologado::updateOrCreate(
            ['item_catalogo_id' => $item->id, 'fornecedor_id' => $fornecedor->id],
            [
                'preco' => [29.90, 12.50, 84.00][$i] ?? 50.0,
                'preferencial' => $i === 0,
                'validade_inicio' => now()->subDays(5)->toDateString(),
                'validade_fim' => now()->addDays(120)->toDateString(),
                'ativo' => true,
                'observacao' => 'Tabela vigente — homologação de demonstração.',
            ]
        );
    }
    echo "Preços homologados: {$itens->count()} (fornecedor {$fornecedor->razao_social})\n";

    // Solicitante + unidade + centro
    $solic = User::where('email', 'solicitante@comendador.com.br')->first();
    $unidadeId = DB::table('unidade_user')->where('user_id', $solic->id)->value('unidade_id');
    $unidade = Unidade::withoutGlobalScopes()->find($unidadeId);
    $centro = CentroCusto::withoutGlobalScopes()->where('unidade_id', $unidade->id)->first();

    // Requisição expressa em aguardando_triagem
    $itensReq = [
        [$itens[0], 4, 29.90],
        [$itens[1], 10, 12.50],
    ];
    $total = array_sum(array_map(fn ($r) => $r[1] * $r[2], $itensReq));

    $faixa = FaixaAlcada::whereNull('deleted_at')->where('ativo', true)->where('is_emergencial', false)
        ->where('valor_minimo', '<=', $total)
        ->where(fn ($q) => $q->whereNull('valor_maximo')->orWhere('valor_maximo', '>=', $total))
        ->first();

    $req = Requisicao::create([
        'solicitante_id' => $solic->id,
        'unidade_id' => $unidade->id,
        'centro_custo_id' => $centro->id,
        'status' => StatusRequisicao::AguardandoTriagem,
        'urgente' => true,
        'is_emergencial' => false,
        'expressa' => true,
        'faixa_alcada_id' => $faixa?->id,
        'submetida_em' => now(),
    ]);
    $req->update(['codigo' => 'REQ-' . now()->year . '-' . str_pad((string) $req->id, 6, '0', STR_PAD_LEFT)]);

    foreach ($itensReq as [$item, $qtd, $preco]) {
        ItemRequisicao::create([
            'requisicao_id' => $req->id,
            'descricao' => $item->descricao,
            'quantidade' => $qtd,
            'unidade_medida' => $item->unidade_medida ?? 'un',
            'valor_unitario_estimado' => $preco,
            'item_catalogo_id' => $item->id,
            'avulso' => false,
        ]);
    }
    echo "Requisição expressa criada: {$req->codigo} (total R$ " . number_format($total, 2, ',', '.') . ")\n";
    echo "Elegível via expressa: " . ($req->avaliarViaExpressa() !== null ? 'SIM' : 'NAO') . "\n";
});

echo "Seed concluído.\n";
