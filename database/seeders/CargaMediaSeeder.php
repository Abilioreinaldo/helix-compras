<?php

namespace Database\Seeders;

use App\Actions\TransferirEstoqueAction;
use App\Enums\NivelAlcada;
use App\Enums\Perfil;
use App\Enums\StatusAprovacao;
use App\Enums\StatusInventario;
use App\Enums\StatusPedidoCompra;
use App\Enums\StatusRequisicao;
use App\Enums\StatusRequisicaoMaterial;
use App\Enums\TipoMovimentacao;
use App\Models\Aprovacao;
use App\Models\CatalogoItem;
use App\Models\CentroCusto;
use App\Models\Cotacao;
use App\Models\EstoqueMinimo;
use App\Models\FaixaAlcada;
use App\Models\Fornecedor;
use App\Models\ItemInventario;
use App\Models\LoteEstoque;
use App\Models\MovimentacaoEstoque;
use App\Models\PedidoCompra;
use App\Models\RateioCentral;
use App\Models\RateioUnidade;
use App\Models\Recebimento;
use App\Models\Requisicao;
use App\Models\RequisicaoMaterial;
use App\Models\SaldoEstoque;
use App\Models\SessaoInventario;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Carga média: popula todas as tabelas com volume realista para navegar o sistema.
 * Roda DEPOIS dos seeders base (usuários nomeados, unidades, alçadas, catálogo, fornecedores).
 */
class CargaMediaSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@comendador.com.br')->firstOrFail();
        $compradora = User::where('email', 'compradora@comendador.com.br')->firstOrFail();
        $almoxarife = User::where('email', 'almoxarife@comendador.com.br')->firstOrFail();
        $solicitante = User::where('email', 'solicitante@comendador.com.br')->firstOrFail();

        $unidades = Unidade::withoutGlobalScopes()->get();

        // Almoxarife nomeado em TODAS as unidades (para ver/transferir saldos em todas).
        foreach ($unidades as $u) {
            if (! $u->usuarios()->where('users.id', $almoxarife->id)->wherePivot('perfil', Perfil::Almoxarife->value)->exists()) {
                $u->usuarios()->attach($almoxarife->id, ['perfil' => Perfil::Almoxarife->value, 'nivel_alcada' => null]);
            }
        }

        $this->referencias($unidades, $solicitante);
        [$catalogos, $fornecedores] = [CatalogoItem::all(), Fornecedor::all()];

        $this->centros($unidades);
        $this->estoque($unidades, $catalogos, $almoxarife);
        $this->requisicoes($unidades, $solicitante, $compradora, $fornecedores, $catalogos);
        $this->pedidosERecebimentos($unidades, $compradora, $almoxarife, $fornecedores);
        $this->rim($unidades, $solicitante, $almoxarife);
        $this->inventarios($unidades, $almoxarife);
        $this->estoqueMinimos($unidades, $catalogos);
        $this->rateios($unidades, $admin);
        $this->transferencias($almoxarife, $admin);
    }

    private function referencias($unidades, User $solicitante): void
    {
        // Mais catálogo (alguns controla_lote), fornecedores, usuários extras.
        CatalogoItem::factory()->count(24)->create();
        CatalogoItem::factory()->count(6)->create(['controla_lote' => true]);
        Fornecedor::factory()->count(8)->homologado()->create();
        Fornecedor::factory()->count(3)->create(['homologado' => false]);

        // Solicitantes e aprovadores extras vinculados às unidades.
        foreach ($unidades as $u) {
            $s = User::factory()->create();
            $u->usuarios()->attach($s->id, ['perfil' => Perfil::Solicitante->value, 'nivel_alcada' => null]);
            $a = User::factory()->create();
            $u->usuarios()->attach($a->id, ['perfil' => Perfil::Aprovador->value, 'nivel_alcada' => NivelAlcada::Gestor->value]);
        }
    }

    private function centros($unidades): void
    {
        foreach ($unidades as $u) {
            CentroCusto::factory()->count(3)->create(['unidade_id' => $u->id]);
        }
    }

    private function estoque($unidades, $catalogos, User $almoxarife): void
    {
        $controlaLote = $catalogos->where('controla_lote', true)->values();

        foreach ($unidades as $u) {
            // Saldos de catálogo (sem lote)
            foreach ($catalogos->where('controla_lote', false)->random(min(10, $catalogos->count())) as $cat) {
                $qtd = fake()->randomFloat(3, 5, 300);
                $cmp = fake()->randomFloat(4, 2, 400);
                $saldo = SaldoEstoque::factory()->create([
                    'unidade_id' => $u->id,
                    'deposito' => fake()->randomElement(['Depósito Central', 'Almoxarifado A', 'Pátio']),
                    'descricao_item' => $cat->descricao,
                    'descricao_normalizada' => SaldoEstoque::normalizarDescricao($cat->descricao),
                    'item_catalogo_id' => $cat->id,
                    'quantidade' => $qtd,
                    'custo_medio_ponderado' => $cmp,
                    'valor_total' => round($qtd * $cmp, 2),
                ]);
                $this->movimentacoesDoSaldo($saldo, $almoxarife);
            }

            // 2 saldos controla_lote com lotes
            foreach ($controlaLote->random(min(2, $controlaLote->count())) as $cat) {
                $lotesQtd = [fake()->randomFloat(3, 5, 80), fake()->randomFloat(3, 5, 80)];
                $total = array_sum($lotesQtd);
                $cmp = fake()->randomFloat(4, 10, 200);
                $saldo = SaldoEstoque::factory()->create([
                    'unidade_id' => $u->id,
                    'deposito' => 'Depósito Central',
                    'descricao_item' => $cat->descricao,
                    'descricao_normalizada' => SaldoEstoque::normalizarDescricao($cat->descricao),
                    'item_catalogo_id' => $cat->id,
                    'quantidade' => $total,
                    'custo_medio_ponderado' => $cmp,
                    'valor_total' => round($total * $cmp, 2),
                ]);
                foreach ($lotesQtd as $i => $q) {
                    LoteEstoque::factory()->create([
                        'saldo_estoque_id' => $saldo->id,
                        'numero_lote' => 'L'.now()->format('y').Str::upper(Str::random(4)),
                        'validade' => fake()->randomElement([now()->addMonths(fake()->numberBetween(1, 18))->toDateString(), now()->subMonths(fake()->numberBetween(1, 6))->toDateString(), null]),
                        'quantidade' => $q,
                        'fundido_para_id' => null,
                    ]);
                }
            }
        }
    }

    private function movimentacoesDoSaldo(SaldoEstoque $saldo, User $almoxarife): void
    {
        $cmp = (float) $saldo->custo_medio_ponderado;
        // 1 entrada e 1-2 saídas, algumas datadas no mês anterior (alimenta o rateio).
        MovimentacaoEstoque::create([
            'saldo_estoque_id' => $saldo->id, 'tipo' => TipoMovimentacao::Entrada, 'quantidade' => fake()->randomFloat(3, 10, 100),
            'custo_unitario' => $cmp, 'valor_total' => round($cmp * 50, 2), 'motivo' => 'Carga inicial', 'registrado_por' => $almoxarife->id,
        ]);
        foreach (range(1, fake()->numberBetween(1, 3)) as $i) {
            $q = fake()->randomFloat(3, 1, 20);
            $mov = MovimentacaoEstoque::create([
                'saldo_estoque_id' => $saldo->id, 'tipo' => TipoMovimentacao::Saida, 'quantidade' => $q,
                'custo_unitario' => $cmp, 'valor_total' => round($q * $cmp, 2), 'motivo' => 'Consumo', 'registrado_por' => $almoxarife->id,
            ]);
            // Metade no mês anterior (para o rateio ter base de consumo).
            if ($i === 1) {
                MovimentacaoEstoque::where('id', $mov->id)->update(['created_at' => now()->subMonthNoOverflow()->startOfMonth()->addDays(10)]);
            }
        }
    }

    private function requisicoes($unidades, User $solicitante, User $compradora, $fornecedores, $catalogos): void
    {
        $statuses = [
            StatusRequisicao::Rascunho, StatusRequisicao::AguardandoTriagem, StatusRequisicao::EmTriagem,
            StatusRequisicao::EmCotacao, StatusRequisicao::CotacaoConcluida, StatusRequisicao::AguardandoAprovacao,
            StatusRequisicao::Aprovada, StatusRequisicao::EmCompra, StatusRequisicao::Recebida,
            StatusRequisicao::Concluida, StatusRequisicao::Reprovada, StatusRequisicao::Devolvida, StatusRequisicao::Cancelada,
        ];
        $faixa = FaixaAlcada::where('is_emergencial', false)->first();

        $n = 0;
        foreach (range(1, 32) as $i) {
            $status = $statuses[$i % count($statuses)];
            $unidade = $unidades->random();
            $centro = CentroCusto::withoutGlobalScopes()->where('unidade_id', $unidade->id)->inRandomOrder()->first();

            $req = Requisicao::create([
                'solicitante_id' => $solicitante->id,
                'unidade_id' => $unidade->id,
                'centro_custo_id' => $centro?->id,
                'status' => $status,
                'urgente' => fake()->boolean(20),
                'is_emergencial' => fake()->boolean(10),
                'codigo' => $status === StatusRequisicao::Rascunho ? null : 'REQ-'.now()->year.'-'.str_pad((string) (++$n + 100), 6, '0', STR_PAD_LEFT),
                'faixa_alcada_id' => $status === StatusRequisicao::Rascunho ? null : $faixa?->id,
                'submetida_em' => $status === StatusRequisicao::Rascunho ? null : now()->subDays(fake()->numberBetween(1, 20)),
                'aprovacao_iniciada_em' => $status === StatusRequisicao::AguardandoAprovacao ? now()->subHours(fake()->numberBetween(50, 120)) : null,
                'ciclo_aprovacao' => 1,
            ]);

            foreach (range(1, fake()->numberBetween(1, 4)) as $j) {
                $cat = $catalogos->random();
                $req->itens()->create([
                    'descricao' => $cat->descricao,
                    'quantidade' => fake()->numberBetween(1, 50),
                    'unidade_medida' => 'un',
                    'valor_unitario_estimado' => fake()->randomFloat(2, 5, 500),
                    'item_catalogo_id' => fake()->boolean(70) ? $cat->id : null,
                    'avulso' => false,
                ]);
            }

            $req->logs()->create([
                'status_anterior' => StatusRequisicao::Rascunho->value,
                'status_novo' => $status->value,
                'user_id' => $solicitante->id,
                'automatico' => false,
            ]);

            // Cotações para quem está em cotação/concluída/aprovação adiante
            if (in_array($status, [StatusRequisicao::EmCotacao, StatusRequisicao::CotacaoConcluida, StatusRequisicao::AguardandoAprovacao, StatusRequisicao::Aprovada, StatusRequisicao::EmCompra])) {
                $vencedoraIdx = fake()->numberBetween(0, 2);
                foreach (range(0, 2) as $k) {
                    Cotacao::create([
                        'requisicao_id' => $req->id,
                        'fornecedor_id' => $fornecedores->where('homologado', true)->random()->id,
                        'valor' => fake()->randomFloat(2, 500, 30000),
                        'prazo_entrega_dias' => fake()->numberBetween(3, 30),
                        'validade_proposta' => now()->addDays(fake()->numberBetween(5, 60))->toDateString(),
                        'vencedora' => $k === $vencedoraIdx && $status !== StatusRequisicao::EmCotacao,
                        'criada_por' => $compradora->id,
                        'vencedora_definida_em' => $k === $vencedoraIdx && $status !== StatusRequisicao::EmCotacao ? now()->subDays(2) : null,
                    ]);
                }
            }

            // Aprovações (etapas) para quem está aguardando aprovação / aprovada
            if (in_array($status, [StatusRequisicao::AguardandoAprovacao, StatusRequisicao::Aprovada])) {
                $aprovada = $status === StatusRequisicao::Aprovada;
                Aprovacao::create([
                    'requisicao_id' => $req->id, 'etapa_alcada_id' => null, 'ciclo' => 1, 'ordem' => 1,
                    'nivel_exigido' => NivelAlcada::Gestor->value, 'obrigatoria_emergencial' => false,
                    'status' => $aprovada ? StatusAprovacao::Aprovada->value : StatusAprovacao::Pendente->value,
                    'aprovador_id' => $aprovada ? $compradora->id : null,
                    'decidida_em' => $aprovada ? now()->subDay() : null,
                ]);
            }
        }
    }

    private function pedidosERecebimentos($unidades, User $compradora, User $almoxarife, $fornecedores): void
    {
        // Alguns PCs com itens vinculados a requisições aprovadas + recebimentos parciais/totais.
        $reqsAprovadas = Requisicao::withoutGlobalScopes()
            ->whereIn('status', [StatusRequisicao::Aprovada->value, StatusRequisicao::EmCompra->value, StatusRequisicao::Recebida->value])
            ->with('itens', 'cotacoes')
            ->take(8)->get();

        $seq = 0;
        foreach ($reqsAprovadas as $req) {
            $cotacao = $req->cotacoes->firstWhere('vencedora', true) ?? $req->cotacoes->first();
            $itemReq = $req->itens->first();
            if (! $cotacao || ! $itemReq) {
                continue;
            }

            $pc = PedidoCompra::create([
                'numero' => 'PC-'.now()->year.'-'.str_pad((string) (++$seq), 4, '0', STR_PAD_LEFT),
                'status' => StatusPedidoCompra::Emitido->value,
                'fornecedor_id' => $cotacao->fornecedor_id,
                'unidade_id' => $req->unidade_id,
                'criado_por' => $compradora->id,
                'emitido_em' => now()->subDays(fake()->numberBetween(1, 15)),
            ]);

            $item = $pc->itens()->create([
                'requisicao_id' => $req->id,
                'item_requisicao_id' => $itemReq->id,
                'cotacao_id' => $cotacao->id,
                'descricao' => $itemReq->descricao,
                'quantidade' => (float) $itemReq->quantidade,
                'unidade_medida' => 'un',
                'valor_unitario' => fake()->randomFloat(2, 10, 300),
                'valor_total' => fake()->randomFloat(2, 100, 5000),
                'destino' => 'Depósito Central',
                'item_catalogo_id' => $itemReq->item_catalogo_id,
                'avulso' => false,
            ]);

            if (fake()->boolean(70)) {
                $rec = Recebimento::create([
                    'pedido_compra_id' => $pc->id,
                    'almoxarife_id' => $almoxarife->id,
                    'recebido_em' => now()->subDays(fake()->numberBetween(0, 5)),
                    'observacoes' => fake()->boolean(40) ? 'Recebimento conforme nota.' : null,
                ]);
                $rec->itens()->create([
                    'item_pedido_compra_id' => $item->id,
                    'quantidade_recebida' => round((float) $item->quantidade * fake()->randomFloat(2, 0.5, 1), 3),
                ]);
            }
        }
    }

    private function rim($unidades, User $solicitante, User $almoxarife): void
    {
        $saldos = SaldoEstoque::withoutGlobalScopes()->whereNull('fundido_para_id')->where('quantidade', '>', 0)->inRandomOrder()->take(10)->get();
        foreach ($saldos as $saldo) {
            RequisicaoMaterial::create([
                'unidade_id' => $saldo->unidade_id,
                'solicitante_id' => $solicitante->id,
                'saldo_estoque_id' => $saldo->id,
                'quantidade_solicitada' => fake()->randomFloat(3, 1, max(1, (float) $saldo->quantidade / 4)),
                'justificativa' => fake()->sentence(),
                'status' => fake()->randomElement([
                    StatusRequisicaoMaterial::Aberta->value,
                    StatusRequisicaoMaterial::Atendida->value,
                    StatusRequisicaoMaterial::Recusada->value,
                ]),
            ]);
        }
    }

    private function inventarios($unidades, User $almoxarife): void
    {
        foreach ($unidades->take(2) as $u) {
            $sessao = SessaoInventario::create([
                'unidade_id' => $u->id,
                'deposito' => 'Depósito Central',
                'aberta_por' => $almoxarife->id,
                'status' => StatusInventario::EmAndamento->value,
            ]);
            $saldos = SaldoEstoque::withoutGlobalScopes()->where('unidade_id', $u->id)->whereNull('fundido_para_id')->take(5)->get();
            foreach ($saldos as $saldo) {
                ItemInventario::create([
                    'sessao_inventario_id' => $sessao->id,
                    'saldo_estoque_id' => $saldo->id,
                    'quantidade_sistema' => (float) $saldo->quantidade,
                    'quantidade_contada' => fake()->boolean(60) ? (float) $saldo->quantidade + fake()->randomFloat(3, -3, 3) : null,
                ]);
            }
        }
    }

    private function estoqueMinimos($unidades, $catalogos): void
    {
        foreach ($unidades as $u) {
            foreach ($catalogos->random(min(5, $catalogos->count())) as $cat) {
                EstoqueMinimo::firstOrCreate(
                    ['unidade_id' => $u->id, 'item_catalogo_id' => $cat->id],
                    ['quantidade_minima' => fake()->randomFloat(3, 5, 50)]
                );
            }
        }
    }

    private function rateios($unidades, User $admin): void
    {
        // 2 rateios em meses passados, com linha por unidade (factory — popula as tabelas).
        foreach ([now()->subMonthsNoOverflow(1), now()->subMonthsNoOverflow(2)] as $ref) {
            $rateio = RateioCentral::create([
                'mes' => $ref->month, 'ano' => $ref->year,
                'valor_total' => fake()->randomFloat(2, 5000, 40000), 'criado_por' => $admin->id,
            ]);
            $pcts = [];
            foreach ($unidades as $u) {
                $pcts[$u->id] = fake()->randomFloat(4, 0.05, 0.5);
            }
            $soma = array_sum($pcts);
            foreach ($unidades as $u) {
                $pct = round($pcts[$u->id] / $soma, 4);
                $linha = RateioUnidade::create([
                    'rateio_central_id' => $rateio->id,
                    'unidade_id' => $u->id,
                    'percentual_consumo' => $pct,
                    'valor_rateado' => round($pct * (float) $rateio->valor_total, 2),
                ]);
                MovimentacaoEstoque::create([
                    'saldo_estoque_id' => null, 'rateio_unidade_id' => $linha->id,
                    'tipo' => TipoMovimentacao::RateioCentral, 'quantidade' => 0, 'custo_unitario' => 0,
                    'valor_total' => $linha->valor_rateado, 'motivo' => "Rateio {$rateio->mes}/{$rateio->ano}.",
                    'registrado_por' => $admin->id,
                ]);
            }
        }
    }

    private function transferencias(User $almoxarife, User $admin): void
    {
        // Algumas transferências reais (via action) entre unidades, em saldos sem lote.
        $acao = app(TransferirEstoqueAction::class);
        for ($i = 0; $i < 4; $i++) {
            $origem = SaldoEstoque::withoutGlobalScopes()
                ->whereNull('fundido_para_id')->where('quantidade', '>', 10)
                ->inRandomOrder()->first();
            if (! $origem || $origem->controlaLote()) {
                continue;
            }
            $destino = Unidade::withoutGlobalScopes()->where('id', '!=', $origem->unidade_id)->inRandomOrder()->first();
            if (! $destino) {
                continue;
            }
            try {
                $acao->execute($origem, $destino, fake()->randomFloat(3, 1, (float) $origem->quantidade / 3), 'Realocação demo', $admin);
            } catch (\Throwable $e) {
                // ignora colisões/edge da carga aleatória
            }
        }
    }
}
