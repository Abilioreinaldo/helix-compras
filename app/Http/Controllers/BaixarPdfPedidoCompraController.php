<?php

namespace App\Http\Controllers;

use App\Enums\Perfil;
use App\Enums\StatusPedidoCompra;
use App\Models\Aprovacao;
use App\Models\PedidoCompra;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class BaixarPdfPedidoCompraController extends Controller
{
    public function __invoke(int $id): Response
    {
        abort_unless(auth()->user()->temPerfil(Perfil::CompradoraSenior), 403);

        $pedido = PedidoCompra::withoutGlobalScopes()
            ->with([
                'itens.itemRequisicao',
                'itens.requisicao.solicitante',
                'fornecedor',
                'unidade',
                'emissor',
            ])
            ->findOrFail($id);

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

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$pedido->numero}.pdf\"",
        ]);
    }
}
