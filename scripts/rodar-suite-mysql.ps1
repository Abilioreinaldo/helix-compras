<#
.SYNOPSIS
    Roda a suíte completa contra um MySQL real, num banco DESCARTÁVEL.

.DESCRIPTION
    Produção é MySQL; os testes locais rodam em SQLite, que esconde bugs de dialeto
    (FK/índice/sintaxe). Este checker pega esses bugs antes do go-live.

    NÃO lê nem modifica o .env. Cria o banco, roda `php artisan test --compact` com
    DB_CONNECTION=mysql, e DROPA o banco no finally (mesmo se os testes falharem).

    Segurança: a senha NUNCA é hardcoded (vem de -DbPassword ou $env:DB_PASSWORD); e o nome
    do banco precisa parecer DESCARTÁVEL (identificador simples terminado em _test ou _tmp),
    porque o banco é apagado ao fim.

.PARAMETER DbHost
    Host do MySQL. Padrão: $env:DB_HOST ou 127.0.0.1.
.PARAMETER DbPort
    Porta. Padrão: $env:DB_PORT ou 3306.
.PARAMETER DbUsername
    Usuário. Padrão: $env:DB_USERNAME. Obrigatório.
.PARAMETER DbPassword
    Senha. Padrão: $env:DB_PASSWORD. Nunca hardcoded.
.PARAMETER DbDatabase
    Banco DESCARTÁVEL (sufixo _test/_tmp). Padrão: $env:DB_DATABASE. Obrigatório.

.EXAMPLE
    $env:DB_PASSWORD = '<senha>'
    ./scripts/rodar-suite-mysql.ps1 -DbUsername root -DbDatabase comendador_suite_test

.EXAMPLE
    $env:DB_USERNAME='root'; $env:DB_PASSWORD='<senha>'; $env:DB_DATABASE='comendador_suite_test'
    ./scripts/rodar-suite-mysql.ps1
#>
[CmdletBinding()]
param(
    [string]$DbHost = $env:DB_HOST,
    [string]$DbPort = $env:DB_PORT,
    [string]$DbUsername = $env:DB_USERNAME,
    [string]$DbPassword = $env:DB_PASSWORD,
    [string]$DbDatabase = $env:DB_DATABASE
)

$ErrorActionPreference = 'Stop'

if ([string]::IsNullOrWhiteSpace($DbHost)) { $DbHost = '127.0.0.1' }
if ([string]::IsNullOrWhiteSpace($DbPort)) { $DbPort = '3306' }

if ([string]::IsNullOrWhiteSpace($DbUsername)) {
    Write-Error 'Informe o usuario: -DbUsername ou $env:DB_USERNAME.'
    exit 2
}
if ($null -eq $DbPassword) { $DbPassword = '' }   # senha vazia é permitida; nunca hardcoded
if ([string]::IsNullOrWhiteSpace($DbDatabase)) {
    Write-Error 'Informe o banco descartavel: -DbDatabase ou $env:DB_DATABASE (sufixo _test/_tmp).'
    exit 2
}

# Guarda de segurança: o banco SERÁ dropado. Exige identificador simples que pareça
# descartável — evita apagar um banco de verdade e injeção no CREATE/DROP (DDL não aceita bind).
if ($DbDatabase -notmatch '^[A-Za-z0-9_]+$' -or $DbDatabase -notmatch '(_test|_tmp)$') {
    Write-Error "Recusado: DB_DATABASE='$DbDatabase' precisa casar [A-Za-z0-9_] e terminar em _test ou _tmp (o script DROPA o banco ao fim)."
    exit 2
}

# Raiz do projeto (este script vive em scripts/).
Set-Location (Split-Path $PSScriptRoot -Parent)

# Env temporário: aponta a app/suíte para o MySQL no banco descartável.
$env:DB_CONNECTION = 'mysql'
$env:DB_HOST = $DbHost
$env:DB_PORT = $DbPort
$env:DB_DATABASE = $DbDatabase
$env:DB_USERNAME = $DbUsername
$env:DB_PASSWORD = $DbPassword
$env:_DSN = "mysql:host=$DbHost;port=$DbPort"

$exitCode = 1
try {
    php -r "(new PDO(getenv('_DSN'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]))->exec('CREATE DATABASE IF NOT EXISTS ' . getenv('DB_DATABASE') . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');"
    Write-Output "- banco descartavel '$DbDatabase' criado; rodando a suite em MySQL ($DbHost`:$DbPort)..."

    php artisan test --compact
    $exitCode = $LASTEXITCODE
}
finally {
    php -r "(new PDO(getenv('_DSN'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]))->exec('DROP DATABASE IF EXISTS ' . getenv('DB_DATABASE') . ';');"
    Write-Output "- banco descartavel '$DbDatabase' removido"
    Remove-Item Env:DB_CONNECTION, Env:DB_HOST, Env:DB_PORT, Env:DB_DATABASE, Env:DB_USERNAME, Env:DB_PASSWORD, Env:_DSN -ErrorAction SilentlyContinue
}

exit $exitCode
