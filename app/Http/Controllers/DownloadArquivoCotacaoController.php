<?php

namespace App\Http\Controllers;

use App\Enums\Perfil;
use App\Models\Cotacao;
use App\Support\AuditoriaDownload;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadArquivoCotacaoController extends Controller
{
    /**
     * COMPRAS-2 (4ª auditoria): o tipo de retorno era `Illuminate\Http\Response`, mas o
     * `Storage::download()` devolve `StreamedResponse` — a rota dava 500 SEMPRE (TypeError)
     * e nenhum teste a cobria. E a saída do anexo (proposta comercial do fornecedor) não
     * deixava trilha de auditoria.
     */
    public function __invoke(Cotacao $cotacao, AuditoriaDownload $auditoria): StreamedResponse
    {
        abort_unless(auth()->user()->temPerfil(Perfil::CompradoraSenior), 403);

        // SEGUNDA TRANCA (2ª auditoria adversarial): o route-model binding resolve pelo
        // `newQuery()` e hoje aplica o escopo de tenant, mas isso é um filtro de CONSULTA
        // — qualquer `withoutGlobalScope` que entre no caminho (ou um binding explícito)
        // o dispensa. A posse do registro é decidida pela policy, que recebe o registro.
        abort_unless(auth()->user()->can('operar', $cotacao), 403);

        abort_unless($cotacao->arquivo_path && Storage::disk('local')->exists($cotacao->arquivo_path), 404);

        $nome = $cotacao->arquivo_nome_original ?? basename($cotacao->arquivo_path);

        // Só o que SAI é auditado como saída: depois da autorização e da existência do arquivo.
        $auditoria->registrar('compras.cotacao_arquivo_baixado', $cotacao, [
            'arquivo' => $nome,
            'requisicao_id' => $cotacao->requisicao_id,
            'fornecedor_id' => $cotacao->fornecedor_id,
        ]);

        return Storage::disk('local')->download($cotacao->arquivo_path, $nome);
    }
}
