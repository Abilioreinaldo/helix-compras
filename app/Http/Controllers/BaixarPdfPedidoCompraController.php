<?php

namespace App\Http\Controllers;

use App\Enums\Perfil;
use App\Enums\StatusPedidoCompra;
use App\Models\Aprovacao;
use App\Models\PedidoCompra;
use App\Models\Scopes\UnidadeScope;
use App\Support\AuditoriaDownload;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class BaixarPdfPedidoCompraController extends Controller
{
    public function __invoke(int $id, AuditoriaDownload $auditoria): Response
    {
        abort_unless(auth()->user()->temPerfil(Perfil::CompradoraSenior), 403);

        $pedido = PedidoCompra::withoutGlobalScope(UnidadeScope::class)
            ->with([
                'itens.itemRequisicao',
                'itens.requisicao.solicitante',
                'fornecedor',
                'unidade',
                'emissor',
            ])
            ->findOrFail($id);

        // SEGUNDA TRANCA (2ª auditoria adversarial): o `withoutGlobalScope` dispensa o
        // filtro de UNIDADE e o de tenant é filtro de CONSULTA. Quem recebe o registro e
        // decide a posse é a policy — sem isto, `temPerfil(CompradoraSenior)` era a
        // única barreira entre um id de URL e o PDF completo do pedido (fornecedor,
        // preços, aprovadores) de outra empresa.
        abort_unless(auth()->user()->can('operar', $pedido), 403);

        abort_unless($pedido->status === StatusPedidoCompra::Emitido, 404);

        // Carregar aprovadores por requisição (última aprovação de cada requisição)
        $aprovadores = [];
        foreach ($pedido->itens->pluck('requisicao_id')->unique() as $reqId) {
            $aprovacao = Aprovacao::where('requisicao_id', $reqId)
                ->where('status', 'aprovada')
                ->with('aprovador')
                ->orderByDesc('decidida_em')
                ->first();
            if ($aprovacao) {
                $aprovadores[$reqId] = $aprovacao;
            }
        }

        $itensPorDestino = $pedido->itens->groupBy(fn ($item) => $item->destino ?? 'Não definido');

        $pdf = Pdf::loadView('pdf.pedido-compra', compact('pedido', 'itensPorDestino', 'aprovadores'));

        // Fallback defensivo: PC Emitido sempre tem numero no fluxo real, mas se faltar o nome
        // não pode virar ".pdf" (o browser salvaria sem extensão).
        $nomeArquivo = $pedido->numero ?: 'pedido-compra-'.$pedido->id;

        $conteudo = $pdf->output();

        // COMPRAS-2 (4ª auditoria): o PDF leva fornecedor, preços e aprovadores — a saída
        // fica na trilha (quem, quando, de onde), depois de renderizar e antes de entregar.
        $auditoria->registrar('compras.pedido_pdf_baixado', $pedido, [
            'numero' => $pedido->numero,
            'fornecedor_id' => $pedido->fornecedor_id,
        ]);

        return response($conteudo, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$nomeArquivo}.pdf\"",
        ]);
    }
}
