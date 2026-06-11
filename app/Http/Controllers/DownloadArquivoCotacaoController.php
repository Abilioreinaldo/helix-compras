<?php

namespace App\Http\Controllers;

use App\Enums\Perfil;
use App\Models\Cotacao;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class DownloadArquivoCotacaoController extends Controller
{
    public function __invoke(Cotacao $cotacao): Response
    {
        abort_unless(auth()->user()->temPerfil(Perfil::CompradoraSenior), 403);
        abort_unless($cotacao->arquivo_path && Storage::disk('local')->exists($cotacao->arquivo_path), 404);

        return Storage::disk('local')->download(
            $cotacao->arquivo_path,
            $cotacao->arquivo_nome_original ?? basename($cotacao->arquivo_path)
        );
    }
}
