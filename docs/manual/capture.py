"""
Captura screenshots reais do sistema (Playwright) para o Manual do Usuário.
Loga com cada perfil e salva PNGs em docs/manual/assets/.

Uso: python docs/manual/capture.py
Requer o app rodando em http://localhost:8125 e o banco de dev populado.
"""
import sys
import pathlib
from playwright.sync_api import sync_playwright

BASE = "http://localhost:8125"
ASSETS = pathlib.Path(__file__).parent / "assets"
ASSETS.mkdir(parents=True, exist_ok=True)
PASSWORD = "senha@123"
VIEWPORT = {"width": 1440, "height": 900}

# perfil -> (email, [(rota, arquivo), ...])
PLAN = {
    "admin": ("admin@comendador.com.br", [
        ("/dashboard", "01-dashboard"),
        ("/admin/catalogo-itens", "20-admin-catalogo"),
        ("/admin/unidades", "21-admin-unidades"),
        ("/admin/usuarios", "22-admin-usuarios"),
        ("/admin/fornecedores", "23-admin-fornecedores"),
        ("/admin/alcadas", "24-admin-alcadas"),
        ("/admin/centros-custo", "25-admin-centros-custo"),
        ("/admin/reconciliacao-saldos", "26-admin-reconciliacao-saldos"),
        ("/relatorios/gastos-cc", "40-rel-gastos-cc"),
        ("/relatorios/custo-obra", "41-rel-custo-obra"),
        ("/relatorios/posicao-estoque", "42-rel-posicao-estoque"),
        ("/relatorios/comparativo-unidades", "43-rel-comparativo-unidades"),
        ("/relatorios/tempo-aprovacao", "44-rel-tempo-aprovacao"),
    ]),
    "solicitante": ("solicitante@comendador.com.br", [
        ("/requisicoes", "10-requisicoes-lista"),
        ("/requisicoes/nova", "11-requisicao-nova"),
    ]),
    "compradora": ("compradora@comendador.com.br", [
        ("/compradora/triagem", "12-compradora-triagem"),
        ("/cotacoes", "13-compradora-cotacoes"),
        ("/compradora/pedidos", "14-compradora-pedidos"),
        ("/compradora/itens-a-repor", "15-compradora-itens-repor"),
    ]),
    "gestor": ("gestor@comendador.com.br", [
        ("/aprovacoes", "16-aprovacoes-fila"),
    ]),
    "almoxarife": ("almoxarife@comendador.com.br", [
        ("/almoxarife/recebimentos", "30-almox-recebimentos"),
        ("/almoxarife/estoque", "31-almox-estoque"),
        ("/almoxarife/mapa-estoque", "32-almox-mapa-estoque"),
        ("/almoxarife/inventario", "33-almox-inventario"),
    ]),
    "financeiro": ("financeiro@comendador.com.br", [
        ("/pagamentos", "34-fin-pagamentos"),
        ("/pagamentos/reconciliacao", "35-fin-reconciliacao"),
        ("/pagamentos/agendamentos", "36-fin-agendamentos"),
    ]),
}


def login(page, email):
    page.goto(f"{BASE}/login", wait_until="networkidle")
    page.fill("#email", email)
    page.fill("#senha", PASSWORD)
    page.click("button[type=submit]")
    try:
        page.wait_for_url("**/dashboard", timeout=8000)
    except Exception:
        page.wait_for_timeout(1500)


def shot(page, rota, arquivo):
    try:
        page.goto(f"{BASE}{rota}", wait_until="networkidle", timeout=15000)
        page.wait_for_timeout(900)
        dest = ASSETS / f"{arquivo}.png"
        page.screenshot(path=str(dest), full_page=True)
        print(f"  OK  {arquivo:32s} <- {rota}")
        return True
    except Exception as e:
        print(f"  ERRO {arquivo:31s} <- {rota}: {type(e).__name__} {e}")
        return False


def main():
    captured = 0
    with sync_playwright() as p:
        browser = p.chromium.launch()
        # Login (sem auth) — captura a tela de login uma vez
        ctx = browser.new_context(viewport=VIEWPORT)
        page = ctx.new_page()
        page.goto(f"{BASE}/login", wait_until="networkidle")
        page.wait_for_timeout(600)
        page.screenshot(path=str(ASSETS / "00-login.png"), full_page=True)
        print("  OK  00-login                       <- /login")
        captured += 1
        ctx.close()

        for perfil, (email, rotas) in PLAN.items():
            print(f"[{perfil}] {email}")
            ctx = browser.new_context(viewport=VIEWPORT)
            page = ctx.new_page()
            login(page, email)
            for rota, arquivo in rotas:
                if shot(page, rota, arquivo):
                    captured += 1
            ctx.close()

        browser.close()
    print(f"\nTotal capturado: {captured} screenshots em {ASSETS}")


if __name__ == "__main__":
    main()
